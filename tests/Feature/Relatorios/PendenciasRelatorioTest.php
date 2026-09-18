<?php

namespace Tests\Feature\Relatorios;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\PendenciasRelatorioService;
use App\Services\Relatorios\ReportFilters;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Relatório operacional de pendências/exigências: responde "quantas exigências
 * abertas, vencidas, tempo médio de resposta do requerente e taxa de expiração?"
 * sobre as analysis_pendencies REAIS. O resumo agrega em SQL; o tempo médio de
 * resposta é em minutos CORRIDOS (o prazo é do cidadão, não da SEDUR) e null
 * sem amostras (honesto, nunca um tempo inventado). Gated por
 * consultar-relatorios; consulta e exportação auditadas (RN-002/008).
 */
class PendenciasRelatorioTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $analista;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Carbon::setTestNow('2026-06-15 12:00:00');

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

    private function processo(string $protocolo): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmPendencia,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ]);
    }

    private function pendencia(ViabilityRequest $processo, array $attrs = []): AnalysisPendency
    {
        return AnalysisPendency::factory()->create(array_merge([
            'viability_request_id' => $processo->id,
            'requested_by_user_id' => $this->analista->id,
            'description' => 'Enviar documento comprobatório',
            'status' => AnalysisPendencyStatus::Aberta,
            'due_at' => now()->addDays(5),
        ], $attrs));
    }

    public function test_resumo_agrega_abertas_vencidas_respondidas_e_expiradas(): void
    {
        $processo = $this->processo('VIA-2026-000100');

        $this->pendencia($processo); // aberta no prazo
        $this->pendencia($processo, ['due_at' => now()->subDay()]); // aberta VENCIDA
        $this->pendencia($processo, [
            'status' => AnalysisPendencyStatus::Respondida,
            'created_at' => Carbon::parse('2026-06-10 09:00'),
            'responded_at' => Carbon::parse('2026-06-12 09:00'), // 2880 min corridos
        ]);
        $this->pendencia($processo, [
            'status' => AnalysisPendencyStatus::Expirada,
            'created_at' => Carbon::parse('2026-06-01 09:00'),
            'due_at' => Carbon::parse('2026-06-05 09:00'),
        ]);

        $resumo = app(PendenciasRelatorioService::class)->resumo(ReportFilters::fromArray([]));

        $this->assertSame(2, $resumo['abertas']);
        $this->assertSame(1, $resumo['vencidas']);
        $this->assertSame(1, $resumo['respondidas']);
        $this->assertSame(1, $resumo['expiradas']);
        $this->assertSame(2880, $resumo['tempo_medio_resposta_minutos']);
    }

    public function test_tempo_medio_de_resposta_e_null_sem_amostras(): void
    {
        $this->pendencia($this->processo('VIA-2026-000101'));

        $resumo = app(PendenciasRelatorioService::class)->resumo(ReportFilters::fromArray([]));

        $this->assertNull($resumo['tempo_medio_resposta_minutos']);
    }

    public function test_builder_filtra_por_status_e_periodo(): void
    {
        $processo = $this->processo('VIA-2026-000102');
        $aberta = $this->pendencia($processo, ['created_at' => Carbon::parse('2026-06-14 10:00')]);
        $this->pendencia($processo, [
            'status' => AnalysisPendencyStatus::Respondida,
            'created_at' => Carbon::parse('2026-06-14 11:00'),
            'responded_at' => Carbon::parse('2026-06-14 12:00'),
        ]);
        $this->pendencia($processo, ['created_at' => Carbon::parse('2026-05-01 10:00')]); // fora do período

        $linhas = app(PendenciasRelatorioService::class)
            ->builder(ReportFilters::fromArray([
                'status_pendencia' => 'aberta',
                'data_de' => '2026-06-01',
                'data_ate' => '2026-06-30',
            ]))
            ->get();

        $this->assertCount(1, $linhas);
        $this->assertSame($aberta->id, $linhas->first()->id);
    }

    public function test_linha_projeta_protocolo_status_e_prazos(): void
    {
        $pendencia = $this->pendencia($this->processo('VIA-2026-000103'));

        $linha = app(PendenciasRelatorioService::class)->linha(
            $pendencia->fresh(['viabilityRequest', 'requestedBy']),
        );

        $this->assertSame('VIA-2026-000103', $linha['processo']);
        $this->assertSame('aberta', $linha['status']);
        $this->assertSame('Ana Analista', $linha['analista']);
        $this->assertNotNull($linha['limite_em']);
        $this->assertNull($linha['respondida_em']);
    }

    public function test_endpoint_renderiza_inertia_com_resumo_e_linhas_auditado(): void
    {
        $this->pendencia($this->processo('VIA-2026-000104'));

        $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/pendencias')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/relatorios/pendencias', false)
                ->where('resumo.abertas', 1)
                ->has('relatorio.data', 1)
                ->has('perPageOptions'));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'consulta-pendencias',
            'result' => 'sucesso',
        ]);
    }

    public function test_exporta_csv_pelo_contrato_unico(): void
    {
        $this->pendencia($this->processo('VIA-2026-000105'));

        $response = $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/pendencias?formato=csv')
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('VIA-2026-000105', $response->streamedContent());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'exporta-pendencias-csv',
            'result' => 'sucesso',
        ]);
    }

    public function test_sem_consultar_relatorios_recebe_403(): void
    {
        $semPermissao = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($semPermissao, 'gestao')
            ->get('/gestao/relatorios/pendencias')
            ->assertForbidden();
    }
}
