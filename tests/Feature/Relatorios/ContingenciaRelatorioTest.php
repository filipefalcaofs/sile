<?php

namespace Tests\Feature\Relatorios;

use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\ContingenciaRelatorioService;
use App\Services\Relatorios\ReportFilters;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Relatório gerencial de atendimento em contingência (HU-148): responde "quanto
 * do volume entra pelo canal de operador e por quê?" — indicador de governança
 * enquanto o canal oficial (Regin) não volta. Recorte por protocolo no período;
 * a participação é null sem base (honesto, nunca 0% fabricado) e o motivo null
 * é rotulado 'não informado' (campo texto livre — nunca somado a motivo real).
 * Gated por consultar-relatorios; consulta e exportação auditadas (RN-002/008).
 */
class ContingenciaRelatorioTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Carbon::setTestNow('2026-06-15 12:00:00');

        $this->operador = User::factory()->gestor()->withAcceptedLgpdTerm()->create(['name' => 'Gil Gestor']);
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

    private function protocolada(string $protocolo, ViabilityRequestOrigin $origem, ?string $motivo = null, string $protocoladoEm = '2026-06-10 10:00'): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => $protocolo,
            'protocoled_at' => Carbon::parse($protocoladoEm),
            'origin' => $origem,
            'contingency_reason' => $motivo,
            'created_by_user_id' => $this->operador->id,
        ]);
    }

    public function test_resumo_agrega_volume_participacao_e_motivos(): void
    {
        $this->protocolada('VIA-2026-000200', ViabilityRequestOrigin::Contingencia, 'Regin indisponível');
        $this->protocolada('VIA-2026-000201', ViabilityRequestOrigin::Contingencia, 'Regin indisponível');
        $this->protocolada('VIA-2026-000202', ViabilityRequestOrigin::Contingencia, null);
        $this->protocolada('VIA-2026-000203', ViabilityRequestOrigin::Portal);
        // Fora do período consultado — RN-005 exclui.
        $this->protocolada('VIA-2024-000204', ViabilityRequestOrigin::Contingencia, 'Regin indisponível', '2024-03-01 10:00');

        $resumo = app(ContingenciaRelatorioService::class)->resumo(
            ReportFilters::fromArray(['data_de' => '2026-06-01', 'data_ate' => '2026-06-30']),
        );

        $this->assertSame(3, $resumo['contingencia']);
        $this->assertSame(4, $resumo['protocoladas']);
        $this->assertSame(75.0, $resumo['participacao']);

        // Ranking de motivos: o null vira 'não informado', nunca somado a motivo real.
        $this->assertSame([
            ['motivo' => 'Regin indisponível', 'total' => 2],
            ['motivo' => 'não informado', 'total' => 1],
        ], $resumo['por_motivo']);
    }

    public function test_participacao_e_null_sem_base_no_periodo(): void
    {
        $resumo = app(ContingenciaRelatorioService::class)->resumo(ReportFilters::fromArray([]));

        $this->assertSame(0, $resumo['contingencia']);
        $this->assertNull($resumo['participacao']);
    }

    public function test_linha_projeta_operador_motivo_e_situacao(): void
    {
        $processo = $this->protocolada('VIA-2026-000210', ViabilityRequestOrigin::Contingencia, 'Regin indisponível');

        $linha = app(ContingenciaRelatorioService::class)->linha($processo->fresh(['createdBy']));

        $this->assertSame('VIA-2026-000210', $linha['processo']);
        $this->assertSame('Regin indisponível', $linha['motivo']);
        $this->assertSame('Gil Gestor', $linha['operador']);
        $this->assertSame('Protocolada', $linha['status_label']);
    }

    public function test_endpoint_renderiza_inertia_com_resumo_e_linhas_auditado(): void
    {
        $this->protocolada('VIA-2026-000220', ViabilityRequestOrigin::Contingencia, 'Regin indisponível');

        $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/contingencia')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/relatorios/contingencia', false)
                ->where('resumo.contingencia', 1)
                ->has('relatorio.data', 1)
                ->has('perPageOptions'));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'consulta-contingencia',
            'result' => 'sucesso',
        ]);
    }

    public function test_exporta_csv_pelo_contrato_unico(): void
    {
        $this->protocolada('VIA-2026-000230', ViabilityRequestOrigin::Contingencia, 'Regin indisponível');

        $response = $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/contingencia?formato=csv')
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('VIA-2026-000230', $response->streamedContent());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'exporta-contingencia-csv',
            'result' => 'sucesso',
        ]);
    }

    public function test_sem_consultar_relatorios_recebe_403(): void
    {
        $semPermissao = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($semPermissao, 'gestao')
            ->get('/gestao/relatorios/contingencia')
            ->assertForbidden();
    }
}
