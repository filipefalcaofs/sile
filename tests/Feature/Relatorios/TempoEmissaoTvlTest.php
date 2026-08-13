<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Relatorios\RelatorioTempoEmissaoTvlService;
use App\Services\Relatorios\ReportFilters;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Tela R2 — SAPS "Tempo de Emissão de TVL". O relatório lista, no modo tabela do
 * SAPS, os processos DECIDIDOS no recorte com o tempo entre a Abertura (created_at)
 * e a Emissão (decided_at). O período recorta pela EMISSÃO (decided_at) — "TVL
 * emitido no recorte" (CA-R2-01); o filtro de CNAE restringe o builder (CA-R2-02);
 * o Excel sai do MESMO recorte (CA-R2-03, RN-005). Degradação honesta: os blocos
 * DAM não são modelados (vêm em branco) e a Revisão via REDESIM não é homologada —
 * o `tipo` das linhas é sempre "Viabilidade" e nenhuma revisão é simulada
 * (CA-R2-05).
 */
class TempoEmissaoTvlTest extends TestCase
{
    use RefreshDatabase;

    private ViabilityServiceType $servico;

    private Cnae $cnaeAlvo;

    private Cnae $cnaeOutro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->servico = ViabilityServiceType::factory()->create([
            'name' => 'Viabilidade — primeiro estabelecimento',
        ]);
        $this->cnaeAlvo = Cnae::factory()->create(['code' => '4712100']);
        $this->cnaeOutro = Cnae::factory()->create(['code' => '5611201']);
    }

    /**
     * Processo DEFERIDO com TVL emitido: decisão com número de produto TVL e
     * decided_at (a Emissão). created_at é a Abertura.
     */
    private function processoDeferido(string $protocolo, string $tvl, string $emitidoEm, ?Cnae $cnae = null): ViabilityRequest
    {
        $r = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Deferida,
            'service_type_id' => $this->servico->id,
            'protocol_number' => $protocolo,
            'created_at' => Carbon::parse($emitidoEm)->subDays(3),
            'protocoled_at' => Carbon::parse($emitidoEm)->subDays(3)->addHour(),
        ]);

        $r->cnaes()->attach(($cnae ?? $this->cnaeAlvo)->id, ['is_primary' => true]);

        ViabilityDecision::factory()->create([
            'viability_request_id' => $r->id,
            'outcome' => DecisionOutcome::Deferida,
            'tvl_product_number' => $tvl,
            'decided_at' => Carbon::parse($emitidoEm),
        ]);

        return $r;
    }

    /**
     * Consultor com consultar-relatorios (o analista não herda a permissão no
     * seeder — o gate da rota é a permissão, não o papel).
     */
    private function consultor(): User
    {
        $user = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $user->givePermissionTo('consultar-relatorios');

        return $user;
    }

    /**
     * @param  array<string, scalar>  $extra
     * @return array<string, scalar>
     */
    private function recorteJunho(array $extra = []): array
    {
        return array_merge([
            'data_de' => '2026-06-01',
            'data_ate' => '2026-06-30',
            'resultado' => 'deferida',
        ], $extra);
    }

    // CA-R2-01: período + Deferido → linhas com TVL emitido no recorte.
    public function test_periodo_e_deferido_traz_linhas_com_tvl_emitido_no_recorte(): void
    {
        $a = $this->processoDeferido('VIA-2026-000001', 'TVL-2026-000001', '2026-06-05 12:00', $this->cnaeAlvo);
        $b = $this->processoDeferido('VIA-2026-000002', 'TVL-2026-000002', '2026-06-10 15:00', $this->cnaeOutro);

        // Emitido FORA do período (2024) — RN-005 exclui.
        $this->processoDeferido('VIA-2024-000003', 'TVL-2024-000003', '2024-03-01 12:00', $this->cnaeAlvo);

        $page = app(RelatorioTempoEmissaoTvlService::class)
            ->consultar(ReportFilters::fromArray($this->recorteJunho()), 15);

        $this->assertSame(2, $page->total());

        $protocolos = collect($page->items())->map(fn (ViabilityRequest $r): ?string => $r->protocol_number)->sort()->values()->all();
        $this->assertSame(['VIA-2026-000001', 'VIA-2026-000002'], $protocolos);

        // Nº de protocolo e Nº de TVL únicos por linha (projeção da tela/export).
        $linhaA = app(RelatorioTempoEmissaoTvlService::class)->linha($a->fresh(['decision', 'serviceType', 'cnaes']));
        $this->assertSame('VIA-2026-000001', $linhaA['processo']);
        $this->assertSame('TVL-2026-000001', $linhaA['tvl_numero']);
        $this->assertTrue($linhaA['tvl_disponivel']);
        $this->assertSame('Viabilidade', $linhaA['tipo']);
        // Duração Emissão−Abertura em minutos ÚTEIS (> 0 — cruza 3 dias corridos).
        $this->assertIsInt($linhaA['duracao_minutos']);
        $this->assertGreaterThan(0, $linhaA['duracao_minutos']);
        // Blocos DAM não modelados — sempre em branco (degradação honesta).
        $this->assertNull($linhaA['dam_numero']);
        $this->assertNull($linhaA['dam_valor']);

        $this->assertNotSame($a->id, $b->id);
    }

    // CA-R2-02: o filtro de CNAE restringe o builder.
    public function test_cnae_restringe_o_builder(): void
    {
        $this->processoDeferido('VIA-2026-000001', 'TVL-2026-000001', '2026-06-05 12:00', $this->cnaeAlvo);
        $this->processoDeferido('VIA-2026-000002', 'TVL-2026-000002', '2026-06-10 15:00', $this->cnaeOutro);

        $page = app(RelatorioTempoEmissaoTvlService::class)
            ->consultar(ReportFilters::fromArray($this->recorteJunho(['cnae' => '4712100'])), 15);

        $this->assertSame(1, $page->total());
        $this->assertSame('VIA-2026-000001', $page->items()[0]->protocol_number);
    }

    // CA-R2-05: nenhum dado de revisão é simulado — tipo=revisao devolve vazio.
    public function test_modo_revisao_nunca_simula_dados(): void
    {
        $this->processoDeferido('VIA-2026-000001', 'TVL-2026-000001', '2026-06-05 12:00', $this->cnaeAlvo);

        $page = app(RelatorioTempoEmissaoTvlService::class)
            ->consultar(ReportFilters::fromArray($this->recorteJunho(['tipo' => 'revisao'])), 15);

        $this->assertSame(0, $page->total());
    }

    public function test_endpoint_renderiza_inertia_com_linhas_e_servicos(): void
    {
        $this->processoDeferido('VIA-2026-000001', 'TVL-2026-000001', '2026-06-05 12:00', $this->cnaeAlvo);

        $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/tempo-emissao-tvl?data_de=2026-06-01&data_ate=2026-06-30&resultado=deferida')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/relatorios/tempo-emissao-tvl', false)
                ->has('relatorio.data', 1)
                ->has('relatorio.data.0', fn (Assert $linha) => $linha
                    ->where('processo', 'VIA-2026-000001')
                    ->where('tvl_numero', 'TVL-2026-000001')
                    ->where('tipo', 'Viabilidade')
                    ->etc())
                ->has('servicos')
                ->has('perPageOptions'));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'consulta-tempo-emissao-tvl',
            'result' => 'sucesso',
        ]);
    }

    // CA-R2-04: "Atividades em residência" no dropdown quando parametrizado (é um
    // tipo de serviço administrável — aparece na lista de serviços do filtro).
    public function test_atividades_em_residencia_aparece_no_dropdown_quando_parametrizado(): void
    {
        ViabilityServiceType::factory()->create(['name' => 'Atividades em residência']);

        $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/tempo-emissao-tvl')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/relatorios/tempo-emissao-tvl', false)
                ->where('servicos', fn ($servicos) => collect($servicos)->contains(
                    fn ($s) => ($s['label'] ?? null) === 'Atividades em residência',
                )));
    }

    // CA-R2-03: o Excel sai do MESMO recorte da tela (RN-005) pelo contrato único.
    public function test_exporta_xlsx_pelo_contrato_unico(): void
    {
        $this->processoDeferido('VIA-2026-000001', 'TVL-2026-000001', '2026-06-05 12:00', $this->cnaeAlvo);

        $response = $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/tempo-emissao-tvl?formato=xlsx&data_de=2026-06-01&data_ate=2026-06-30&resultado=deferida')
            ->assertOk();

        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type'),
        );
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('content-disposition'),
        );

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'exporta-tempo-emissao-tvl-xlsx',
            'result' => 'sucesso',
        ]);
    }

    public function test_sem_consultar_relatorios_recebe_403(): void
    {
        $semPermissao = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($semPermissao, 'gestao')
            ->get('/gestao/relatorios/tempo-emissao-tvl')
            ->assertForbidden();
    }
}
