<?php

namespace Tests\Feature\Comunicacao;

use App\Enums\AnalysisStage;
use App\Enums\CommunicationStatus;
use App\Enums\CommunicationType;
use App\Enums\ViabilityRequestStatus;
use App\Models\Communication;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Notifications\ProcessoEscalonadoNotification;
use App\Services\Analise\AnalysisSlaService;
use App\Services\Analise\SlaStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * HU-147 (notificacoes:escalonar-sla): escalonamento por SLA sobre a FONTE ÚNICA
 * de prazo (analysis_due_at + analysis_stage_started_at) lida pelo
 * AnalysisSlaService — a MESMA origem da fila e do badge da Fase 10, sem fonte
 * paralela (CA-02). SlaStatus::Amarelo alerta o analista responsável;
 * SlaStatus::Vermelho escala ao(s) gestor(es) (role parametrizável). O tratamento
 * vem de notificacoes.escalonamento.tratamento (default só notificar — NUNCA
 * decide/transiciona). Idempotente pelo ledger communications (RN-004).
 */
class EscalonarSlaCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow('2026-03-10 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function processoEmAnalise(array $analysis = []): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ]);

        $request->forceFill(array_merge([
            'analysis_stage' => AnalysisStage::Analise,
            'analysis_stage_started_at' => now(),
            'analysis_due_at' => now()->addDays(10),
        ], $analysis))->save();

        return $request;
    }

    public function test_amarelo_alerta_o_analista_responsavel(): void
    {
        NotificationFacade::fake();
        $analista = User::factory()->analista()->create();
        $processo = $this->processoEmAnalise([
            'assigned_user_id' => $analista->id,
            'analysis_stage_started_at' => now()->subDays(9),
            'analysis_due_at' => now()->addDay(), // 90% decorrido, não vencido → Amarelo
        ]);

        $this->artisan('notificacoes:escalonar-sla')->assertSuccessful();

        $this->assertDatabaseHas('communications', [
            'viability_request_id' => $processo->id,
            'recipient_user_id' => $analista->id,
            'type' => CommunicationType::EscalonamentoSla->value,
            'status' => CommunicationStatus::NaFila->value,
        ]);
        NotificationFacade::assertSentTo($analista, ProcessoEscalonadoNotification::class);

        // SÓ notifica: o status do processo não muda (sem decisão automática).
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->refresh()->status);
    }

    public function test_vencido_escala_aos_gestores(): void
    {
        NotificationFacade::fake();
        $analista = User::factory()->analista()->create();
        $gestor1 = User::factory()->gestor()->create();
        $gestor2 = User::factory()->gestor()->create();
        $processo = $this->processoEmAnalise([
            'assigned_user_id' => $analista->id,
            'analysis_stage_started_at' => now()->subDays(20),
            'analysis_due_at' => now()->subDay(), // vencido → Vermelho
        ]);

        $this->artisan('notificacoes:escalonar-sla')->assertSuccessful();

        foreach ([$gestor1, $gestor2] as $gestor) {
            $this->assertDatabaseHas('communications', [
                'viability_request_id' => $processo->id,
                'recipient_user_id' => $gestor->id,
                'type' => CommunicationType::EscalonamentoSla->value,
            ]);
            NotificationFacade::assertSentTo($gestor, ProcessoEscalonadoNotification::class);
        }

        // No vencido, o destinatário é o gestor — o analista NÃO é escalado.
        $this->assertDatabaseMissing('communications', [
            'viability_request_id' => $processo->id,
            'recipient_user_id' => $analista->id,
            'type' => CommunicationType::EscalonamentoSla->value,
        ]);
    }

    public function test_prazo_coincide_com_o_analysis_sla_service_sem_fonte_paralela(): void
    {
        NotificationFacade::fake();
        $analista = User::factory()->analista()->create();
        $gestor = User::factory()->gestor()->create();
        $processo = $this->processoEmAnalise([
            'assigned_user_id' => $analista->id,
            'analysis_stage_started_at' => now()->subDays(20),
            'analysis_due_at' => now()->subDay(),
        ]);

        // O veredito do comando deve coincidir com o do AnalysisSlaService (CA-02).
        $status = app(AnalysisSlaService::class)
            ->statusFor($processo->analysis_due_at, $processo->analysis_stage_started_at)['status'];
        $this->assertSame(SlaStatus::Vermelho, $status);

        $this->artisan('notificacoes:escalonar-sla')->assertSuccessful();

        $this->assertDatabaseHas('communications', [
            'viability_request_id' => $processo->id,
            'recipient_user_id' => $gestor->id,
            'type' => CommunicationType::EscalonamentoSla->value,
        ]);
    }

    public function test_rodar_duas_vezes_nao_duplica(): void
    {
        NotificationFacade::fake();
        $analista = User::factory()->analista()->create();
        $this->processoEmAnalise([
            'assigned_user_id' => $analista->id,
            'analysis_stage_started_at' => now()->subDays(9),
            'analysis_due_at' => now()->addDay(),
        ]);

        $this->artisan('notificacoes:escalonar-sla')->assertSuccessful();
        $apos1 = Communication::query()->count();

        $this->artisan('notificacoes:escalonar-sla')->assertSuccessful();
        $apos2 = Communication::query()->count();

        $this->assertGreaterThan(0, $apos1);
        $this->assertSame($apos1, $apos2, 'A segunda execução não pode duplicar communications (RN-004).');
    }

    public function test_no_op_honesto_quando_tudo_verde(): void
    {
        NotificationFacade::fake();
        $analista = User::factory()->analista()->create();
        $this->processoEmAnalise([
            'assigned_user_id' => $analista->id,
            'analysis_stage_started_at' => now(),
            'analysis_due_at' => now()->addDays(10), // recém-iniciado → Verde
        ]);

        $this->artisan('notificacoes:escalonar-sla')->assertSuccessful();

        $this->assertSame(0, Communication::query()->count());
        NotificationFacade::assertNothingSent();
    }
}
