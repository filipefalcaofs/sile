<?php

namespace Tests\Feature\Relatorios;

use App\Enums\ViabilityRequestStatus;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\SlaVencimentosService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Relatório operacional de SLA e vencimentos da análise: responde "o que está
 * vencido ou vence em X dias, por setor/analista?" sobre os processos EM
 * ANDAMENTO (em_analise/em_pendencia com analysis_due_at materializado pelo
 * AnalysisSlaService). O resumo agrega em SQL (nunca loop PHP); a linha reusa
 * o semáforo on-the-fly do AnalysisSlaService. A janela de "vencendo" é
 * parametrizável (relatorios.sla.janela_vencimento_dias, default 2). Gated por
 * consultar-relatorios; consulta e exportação auditadas (RN-002/008).
 */
class SlaVencimentosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Sector $setor;

    private User $analista;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Relógio congelado ANTES de qualquer factory: o termo LGPD publicado
        // precisa nascer dentro da janela de teste para o current() achar.
        Carbon::setTestNow('2026-06-15 12:00:00');

        $this->setor = Sector::factory()->create(['name' => 'Setor Viabilidade']);
        $this->analista = User::factory()->analista()->withAcceptedLgpdTerm()->create(['name' => 'Ana Analista']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function consultor(): User
    {
        $user = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $user->givePermissionTo('consultar-relatorios');

        return $user;
    }

    private function emAndamento(string $protocolo, string $dueAt, ?Sector $setor = null): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => $protocolo,
            'protocoled_at' => Carbon::parse($dueAt)->subDays(5),
            'analysis_stage' => 'analise',
            'analysis_stage_started_at' => Carbon::parse($dueAt)->subDays(5),
            'analysis_due_at' => Carbon::parse($dueAt),
            'sector_id' => ($setor ?? $this->setor)->id,
            'assigned_user_id' => $this->analista->id,
        ]);
    }

    public function test_resumo_agrega_em_andamento_vencidos_e_vencendo(): void
    {
        $this->emAndamento('VIA-2026-000001', '2026-06-14 12:00'); // vencido
        $this->emAndamento('VIA-2026-000002', '2026-06-16 12:00'); // vence amanhã (janela default 2 dias)
        $this->emAndamento('VIA-2026-000003', '2026-06-30 12:00'); // no prazo

        // Decidido (fora do andamento) e sem prazo materializado ficam de fora.
        ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Deferida,
            'protocol_number' => 'VIA-2026-000004',
            'analysis_due_at' => Carbon::parse('2026-06-10 12:00'),
        ]);

        $resumo = app(SlaVencimentosService::class)->resumo(ReportFilters::fromArray([]));

        $this->assertSame(3, $resumo['em_andamento']);
        $this->assertSame(1, $resumo['vencidos']);
        $this->assertSame(1, $resumo['vencendo']);
        $this->assertSame(2, $resumo['janela_vencimento_dias']);
    }

    public function test_builder_respeita_filtro_de_setor_e_ordena_pelo_vencimento(): void
    {
        $outroSetor = Sector::factory()->create(['name' => 'Outro Setor']);
        $doSetor = $this->emAndamento('VIA-2026-000010', '2026-06-20 12:00');
        $this->emAndamento('VIA-2026-000011', '2026-06-18 12:00', $outroSetor);

        $linhas = app(SlaVencimentosService::class)
            ->builder(ReportFilters::fromArray(['setor' => $this->setor->id]))
            ->get();

        $this->assertCount(1, $linhas);
        $this->assertSame($doSetor->id, $linhas->first()->id);
    }

    public function test_linha_projeta_semaforo_e_restante_reais(): void
    {
        $vencido = $this->emAndamento('VIA-2026-000020', '2026-06-14 12:00');

        $linha = app(SlaVencimentosService::class)->linha(
            $vencido->fresh(['sector', 'assignedTo']),
        );

        $this->assertSame('VIA-2026-000020', $linha['processo']);
        $this->assertSame('Análise', $linha['etapa']);
        $this->assertSame('Setor Viabilidade', $linha['setor']);
        $this->assertSame('Ana Analista', $linha['analista']);
        $this->assertSame('vermelho', $linha['situacao']);
        $this->assertSame('Vencido', $linha['situacao_label']);
    }

    public function test_endpoint_renderiza_inertia_com_resumo_e_linhas_auditado(): void
    {
        $this->emAndamento('VIA-2026-000030', '2026-06-14 12:00');

        $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/sla')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/relatorios/sla', false)
                ->where('resumo.em_andamento', 1)
                ->where('resumo.vencidos', 1)
                ->has('relatorio.data', 1)
                ->has('setores')
                ->has('analistas')
                ->has('perPageOptions'));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'consulta-sla-vencimentos',
            'result' => 'sucesso',
        ]);
    }

    public function test_exporta_csv_pelo_contrato_unico(): void
    {
        $this->emAndamento('VIA-2026-000040', '2026-06-14 12:00');

        $response = $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/sla?formato=csv')
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('VIA-2026-000040', $response->streamedContent());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'exporta-sla-vencimentos-csv',
            'result' => 'sucesso',
        ]);
    }

    public function test_sem_consultar_relatorios_recebe_403(): void
    {
        $semPermissao = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($semPermissao, 'gestao')
            ->get('/gestao/relatorios/sla')
            ->assertForbidden();
    }
}
