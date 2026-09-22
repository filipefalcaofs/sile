<?php

namespace Tests\Feature\Relatorios;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Enums\CommunicationType;
use App\Enums\ViabilityRequestStatus;
use App\Models\Communication;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\ComunicacoesFalhaService;
use App\Services\Relatorios\ReportFilters;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Consulta operacional de falhas de comunicação (HU-096): responde "quais
 * notificações falharam ou foram bloqueadas, de quais processos?" sobre o
 * ledger REAL de communications — o gestor vê a pendência operacional (nunca
 * uma falha silenciosa). Recorte por período (created_at) e canal; SEM dados
 * do destinatário (LGPD — o cidadão não aparece). Gated por
 * consultar-relatorios; consulta e exportação auditadas (RN-002/008).
 */
class ComunicacoesFalhaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ViabilityRequest $processo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Carbon::setTestNow('2026-06-15 12:00:00');

        $this->processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-2026-000300',
            'protocoled_at' => now(),
        ]);
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

    private function comunicacao(CommunicationStatus $status, array $attrs = []): Communication
    {
        return Communication::factory()->create(array_merge([
            'viability_request_id' => $this->processo->id,
            'channel' => CommunicationChannel::Email,
            'type' => CommunicationType::Resultado,
            'status' => $status,
            'title' => 'Resultado da viabilidade',
            'created_at' => Carbon::parse('2026-06-14 10:00'),
        ], $attrs));
    }

    public function test_resumo_agrega_falhas_e_bloqueios_por_canal(): void
    {
        $this->comunicacao(CommunicationStatus::Falhou, ['error_message' => 'SMTP recusou', 'failed_at' => Carbon::parse('2026-06-14 10:05')]);
        $this->comunicacao(CommunicationStatus::Falhou, ['error_message' => 'SMTP recusou', 'failed_at' => Carbon::parse('2026-06-14 11:05')]);
        $this->comunicacao(CommunicationStatus::Bloqueado, ['channel' => CommunicationChannel::Whatsapp, 'error_message' => 'Gateway indisponível']);
        $this->comunicacao(CommunicationStatus::Enviado, ['sent_at' => Carbon::parse('2026-06-14 10:10')]); // fora do recorte de falhas
        // Fora do período consultado — RN-005 exclui.
        $this->comunicacao(CommunicationStatus::Falhou, ['created_at' => Carbon::parse('2024-03-01 10:00'), 'failed_at' => Carbon::parse('2024-03-01 10:05')]);

        $resumo = app(ComunicacoesFalhaService::class)->resumo(
            ReportFilters::fromArray(['data_de' => '2026-06-01', 'data_ate' => '2026-06-30']),
        );

        $this->assertSame(2, $resumo['falharam']);
        $this->assertSame(1, $resumo['bloqueadas']);
        $this->assertSame([
            ['canal' => 'email', 'canal_label' => 'E-mail', 'total' => 2],
            ['canal' => 'whatsapp', 'canal_label' => 'WhatsApp', 'total' => 1],
        ], $resumo['por_canal']);
    }

    public function test_builder_traz_so_falhas_e_bloqueios_com_protocolo(): void
    {
        $falha = $this->comunicacao(CommunicationStatus::Falhou, ['error_message' => 'SMTP recusou', 'failed_at' => now()]);
        $this->comunicacao(CommunicationStatus::Enviado, ['sent_at' => now()]);

        $linhas = app(ComunicacoesFalhaService::class)->builder(ReportFilters::fromArray([]))->get();

        $this->assertCount(1, $linhas);
        $this->assertSame($falha->id, $linhas->first()->id);
    }

    public function test_linha_projeta_sem_expor_destinatario(): void
    {
        $falha = $this->comunicacao(CommunicationStatus::Falhou, ['error_message' => 'SMTP recusou', 'failed_at' => now()]);

        $linha = app(ComunicacoesFalhaService::class)->linha($falha->fresh(['viabilityRequest']));

        $this->assertSame('VIA-2026-000300', $linha['processo']);
        $this->assertSame('E-mail', $linha['canal_label']);
        $this->assertSame('SMTP recusou', $linha['erro']);
        $this->assertArrayNotHasKey('destinatario', $linha);
    }

    public function test_endpoint_renderiza_inertia_com_resumo_e_linhas_auditado(): void
    {
        $this->comunicacao(CommunicationStatus::Falhou, ['error_message' => 'SMTP recusou', 'failed_at' => now()]);

        $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/comunicacoes-falhas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/relatorios/comunicacoes-falhas', false)
                ->where('resumo.falharam', 1)
                ->has('relatorio.data', 1)
                ->has('perPageOptions'));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'consulta-comunicacoes-falhas',
            'result' => 'sucesso',
        ]);
    }

    public function test_exporta_csv_pelo_contrato_unico(): void
    {
        $this->comunicacao(CommunicationStatus::Falhou, ['error_message' => 'SMTP recusou', 'failed_at' => now()]);

        $response = $this->actingAs($this->consultor(), 'gestao')
            ->get('/gestao/relatorios/comunicacoes-falhas?formato=csv')
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('VIA-2026-000300', $response->streamedContent());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'relatorios',
            'event' => 'exporta-comunicacoes-falhas-csv',
            'result' => 'sucesso',
        ]);
    }

    public function test_sem_consultar_relatorios_recebe_403(): void
    {
        $semPermissao = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($semPermissao, 'gestao')
            ->get('/gestao/relatorios/comunicacoes-falhas')
            ->assertForbidden();
    }
}
