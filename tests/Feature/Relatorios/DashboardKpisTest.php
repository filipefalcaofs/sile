<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestTransition;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\SlaVencimentosService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * KPIs operacionais da home (HU-122): `kpis.operacao` condicionado a
 * `consultar-relatorios`. Os números vêm dos serviços route-free sobre dado
 * real da janela. Sem série histórica persistida o bloco NÃO traz `delta`
 * (anti-fachada CA-03); sem permissão é null; sem dados no período degrada
 * honesto (taxa null, volume 0), nunca um número fabricado.
 */
class DashboardKpisTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Segunda-feira: a janela padrão (últimos 30 dias) fica determinística. */
    private const AGORA = '2026-06-15 12:00:00';

    /** Quarta-feira dentro da janela (dia útil, sem feriado). */
    private const DENTRO_DA_JANELA = '2026-06-10';

    /** Antes da janela: conta para taxa/elegíveis (por decisão/transição), não para volume. */
    private const ANTES_DA_JANELA = '2026-04-01';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    #[Test]
    public function bloco_relatorios_traz_kpis_reais_do_ep15_sem_delta_inventado(): void
    {
        Carbon::setTestNow(self::AGORA);

        $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
        $analista = User::factory()->analista()->create();

        // Volume (HU-123): 2 protocoladas dentro da janela.
        $this->protocolada(self::DENTRO_DA_JANELA.' 09:00:00');
        $this->protocolada(self::DENTRO_DA_JANELA.' 09:00:00');

        // Timeline para o tempo médio de análise (HU-129): protocolada na janela;
        // análise das 14h às 16h da MESMA quarta = 120 minutos úteis. A transição
        // protocolada→em_analise também é 1 elegível do expresso.
        $tempo = $this->protocolada(self::DENTRO_DA_JANELA.' 10:00:00', [
            'created_at' => Carbon::parse(self::DENTRO_DA_JANELA.' 09:00:00'),
        ]);
        $this->transicao($tempo, ViabilityRequestStatus::Protocolada, ViabilityRequestStatus::EmAnalise, self::DENTRO_DA_JANELA.' 14:00:00');
        $this->transicao($tempo, ViabilityRequestStatus::EmAnalise, ViabilityRequestStatus::Deferida, self::DENTRO_DA_JANELA.' 16:00:00');

        // Taxas de deferimento/indeferimento (HU-127/128): 4 decisões na janela —
        // 3 deferidas (2 expressas + 1 técnica) + 1 indeferida → 75% / 25%.
        $this->decisaoExpressa(DecisionOutcome::Deferida, self::DENTRO_DA_JANELA.' 11:00:00');
        $this->decisaoExpressa(DecisionOutcome::Deferida, self::DENTRO_DA_JANELA.' 11:00:00');
        $this->decisaoTecnica(DecisionOutcome::Deferida, $analista->id, self::DENTRO_DA_JANELA.' 11:00:00');
        $this->decisaoTecnica(DecisionOutcome::Indeferida, $analista->id, self::DENTRO_DA_JANELA.' 11:00:00');

        // Taxa de resposta expressa (HU-145): 2 respondidas ÷ 4 elegíveis = 50%.
        // 1 elegível veio da timeline acima; +3 transições protocolada→deferida.
        $this->transicaoElegivel(self::DENTRO_DA_JANELA.' 12:00:00');
        $this->transicaoElegivel(self::DENTRO_DA_JANELA.' 12:00:00');
        $this->transicaoElegivel(self::DENTRO_DA_JANELA.' 12:00:00');

        $this->actingAs($gestor, 'gestao')
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/dashboard')
                ->where('kpis.operacao.protocolos', 3)
                ->where('kpis.operacao.decisoes.total', 4)
                ->where('kpis.operacao.decisoes.expresso', 2)
                ->where('kpis.operacao.decisoes.humano', 2)
                ->where('kpis.operacao.taxa_expressa', fn ($taxa) => (float) $taxa === 50.0)
                ->where('kpis.operacao.meta_expressa', null)
                ->missing('kpis.operacao.delta')
                ->missing('kpis.relatorios'));
    }

    #[Test]
    public function operacao_e_null_sem_consultar_relatorios(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => null,
            'protocoled_at' => now(),
        ]);

        $this->actingAs($analista, 'gestao')
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/dashboard')
                ->where('kpis.operacao', null));
    }

    #[Test]
    public function periodo_sem_dados_degrada_honesto_sem_taxa_fabricada(): void
    {
        Carbon::setTestNow(self::AGORA);

        $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();

        $this->actingAs($gestor, 'gestao')
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/dashboard')
                ->where('kpis.operacao.protocolos', 0)
                ->where('kpis.operacao.decisoes.total', 0)
                ->where('kpis.operacao.taxa_expressa', null)
                ->where('kpis.operacao.serie_fluxo', [])
                ->where('kpis.operacao.estoque_total', 0)
                ->where('kpis.operacao.atrasados', 0));
    }

    #[Test]
    public function atrasados_da_home_coincidem_com_resumo_do_sla(): void
    {
        Carbon::setTestNow(self::AGORA);

        $vencido = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocoled_at' => Carbon::parse(self::DENTRO_DA_JANELA),
            'analysis_due_at' => Carbon::parse('2026-06-14 12:00'),
            'analysis_stage' => 'analise',
            'analysis_stage_started_at' => Carbon::parse('2026-06-10 12:00'),
        ]);
        $this->assertNotNull($vencido->id);

        $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
        $vencidosSla = app(SlaVencimentosService::class)
            ->resumo(ReportFilters::fromArray([]))['vencidos'];

        $this->actingAs($gestor, 'gestao')
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('kpis.operacao.atrasados', $vencidosSla)
                ->where('kpis.operacao.atrasados', 1));
    }

    #[Test]
    public function rascunho_nao_entra_em_protocolos(): void
    {
        Carbon::setTestNow(self::AGORA);
        ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Rascunho,
            'protocoled_at' => Carbon::parse(self::DENTRO_DA_JANELA),
        ]);

        $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();

        $this->actingAs($gestor, 'gestao')
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('kpis.operacao.protocolos', 0));
    }

    /**
     * Solicitação protocolada com data cravada (a base dos indicadores é o
     * conjunto protocolado).
     *
     * @param  array<string, mixed>  $attrs
     */
    private function protocolada(string $protocoledAt, array $attrs = []): ViabilityRequest
    {
        return ViabilityRequest::factory()->create(array_merge([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => null,
            'protocoled_at' => Carbon::parse($protocoledAt),
        ], $attrs));
    }

    private function transicao(ViabilityRequest $r, ViabilityRequestStatus $de, ViabilityRequestStatus $para, string $quando): void
    {
        ViabilityRequestTransition::factory()->create([
            'viability_request_id' => $r->id,
            'from_status' => $de,
            'to_status' => $para,
            'created_at' => Carbon::parse($quando),
        ]);
    }

    /**
     * Decisão expressa (auto-decisão do sistema, sem analista) decidida na janela
     * — conta como "respondida" do expresso e entra nas taxas de deferimento.
     */
    private function decisaoExpressa(DecisionOutcome $outcome, string $decididaEm): void
    {
        ViabilityDecision::factory()->create([
            'viability_request_id' => $this->processoForaDaJanela()->id,
            'flow' => 'expresso',
            'decided_by_user_id' => null,
            'outcome' => $outcome,
            'tvl_product_number' => null,
            'decided_at' => Carbon::parse($decididaEm),
        ]);
    }

    /**
     * Decisão técnica humana (não-expressa) decidida na janela — entra nas taxas,
     * mas NÃO conta como resposta do expresso.
     */
    private function decisaoTecnica(DecisionOutcome $outcome, int $analistaId, string $decididaEm): void
    {
        ViabilityDecision::factory()->create([
            'viability_request_id' => $this->processoForaDaJanela()->id,
            'flow' => 'analise_tecnica',
            'decided_by_user_id' => $analistaId,
            'outcome' => $outcome,
            'tvl_product_number' => null,
            'decided_at' => Carbon::parse($decididaEm),
        ]);
    }

    /**
     * Transição protocolada→deferida na janela = uma entrada elegível no fluxo
     * expresso (denominador da taxa de resposta), sobre processo fora da janela.
     */
    private function transicaoElegivel(string $criadaEm): void
    {
        $this->transicao($this->processoForaDaJanela(), ViabilityRequestStatus::Protocolada, ViabilityRequestStatus::Deferida, $criadaEm);
    }

    /**
     * Processo protocolado ANTES da janela: serve de âncora para decisões/
     * transições decididas/criadas na janela sem inflar o volume corrente.
     */
    private function processoForaDaJanela(): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => null,
            'protocoled_at' => Carbon::parse(self::ANTES_DA_JANELA),
        ]);
    }
}
