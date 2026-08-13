<?php

namespace Tests\Feature\Comunicacao;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\AnalysisStage;
use App\Enums\CommunicationStatus;
use App\Enums\CommunicationType;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\Communication;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Notifications\PrazoVencendoNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * HU-093 (notificacoes:alertar-vencimentos): alerta ANTECIPADO de prazo próximo
 * do vencimento, idempotente e SÓ notificando (sem transição/timeline → sem
 * dupla contagem com a HU-129). Varre os processos em_analise cujo analysis_due_at
 * (FONTE ÚNICA, AnalysisSlaService/Fase 10) cai dentro da antecedência
 * parametrizável (notificacoes.vencimento.antecedencia_dias) e alerta o analista
 * responsável; e as pendências abertas cujo due_at cai na antecedência e alerta o
 * requerente. As notificações percorrem o NotificationDispatcher (multicanal +
 * ledger communications); a idempotência reusa o ledger (RN-004) sem schema novo.
 */
class AlertarVencimentosCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Processo em_analise com os atributos de SLA (fora do fillable), espelhando
     * o helper do ProcessoFilaTest.
     *
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
            'analysis_due_at' => now()->addDays(2),
        ], $analysis))->save();

        return $request;
    }

    public function test_processo_em_analise_vencendo_na_antecedencia_alerta_o_analista(): void
    {
        NotificationFacade::fake();
        $analista = User::factory()->analista()->create();
        $processo = $this->processoEmAnalise([
            'assigned_user_id' => $analista->id,
            'analysis_due_at' => now()->addDays(2), // dentro da antecedência default (3)
        ]);

        $this->artisan('notificacoes:alertar-vencimentos')->assertSuccessful();

        $this->assertDatabaseHas('communications', [
            'viability_request_id' => $processo->id,
            'recipient_user_id' => $analista->id,
            'type' => CommunicationType::PrazoVencendo->value,
            'status' => CommunicationStatus::NaFila->value,
        ]);
        NotificationFacade::assertSentTo($analista, PrazoVencendoNotification::class);

        // SÓ notifica: o status do processo não muda (anti-dupla-contagem HU-129).
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->refresh()->status);
    }

    public function test_processo_fora_da_antecedencia_nao_alerta(): void
    {
        NotificationFacade::fake();
        $analista = User::factory()->analista()->create();
        $processo = $this->processoEmAnalise([
            'assigned_user_id' => $analista->id,
            'analysis_due_at' => now()->addDays(30), // muito além da antecedência (3)
        ]);

        $this->artisan('notificacoes:alertar-vencimentos')->assertSuccessful();

        $this->assertDatabaseMissing('communications', [
            'viability_request_id' => $processo->id,
            'type' => CommunicationType::PrazoVencendo->value,
        ]);
        NotificationFacade::assertNothingSent();
    }

    public function test_pendencia_aberta_vencendo_alerta_o_requerente(): void
    {
        NotificationFacade::fake();
        $requerente = User::factory()->create();
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmPendencia,
            'requester_user_id' => $requerente->id,
            'protocol_number' => 'VIA-'.now()->year.'-900001',
            'protocoled_at' => now(),
        ]);
        AnalysisPendency::factory()->create([
            'viability_request_id' => $processo->id,
            'status' => AnalysisPendencyStatus::Aberta,
            'due_at' => now()->addDays(2), // dentro da antecedência default (3)
        ]);

        $this->artisan('notificacoes:alertar-vencimentos')->assertSuccessful();

        $this->assertDatabaseHas('communications', [
            'viability_request_id' => $processo->id,
            'recipient_user_id' => $requerente->id,
            'type' => CommunicationType::PrazoVencendo->value,
        ]);
        NotificationFacade::assertSentTo($requerente, PrazoVencendoNotification::class);
    }

    public function test_rodar_duas_vezes_nao_duplica_communications(): void
    {
        NotificationFacade::fake();
        $analista = User::factory()->analista()->create();
        $this->processoEmAnalise([
            'assigned_user_id' => $analista->id,
            'analysis_due_at' => now()->addDays(2),
        ]);

        $this->artisan('notificacoes:alertar-vencimentos')->assertSuccessful();
        $aposPrimeira = Communication::query()->count();

        $this->artisan('notificacoes:alertar-vencimentos')->assertSuccessful();
        $aposSegunda = Communication::query()->count();

        $this->assertGreaterThan(0, $aposPrimeira);
        $this->assertSame($aposPrimeira, $aposSegunda, 'A segunda execução não pode duplicar communications (RN-004).');
    }

    public function test_no_op_honesto_quando_nada_esta_na_janela(): void
    {
        NotificationFacade::fake();
        $analista = User::factory()->analista()->create();
        // Processo em_analise, mas com prazo muito além da antecedência.
        $this->processoEmAnalise([
            'assigned_user_id' => $analista->id,
            'analysis_due_at' => now()->addDays(60),
        ]);

        $this->artisan('notificacoes:alertar-vencimentos')->assertSuccessful();

        $this->assertSame(0, Communication::query()->count());
        NotificationFacade::assertNothingSent();
    }
}
