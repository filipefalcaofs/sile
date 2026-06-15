<?php

namespace Tests\Feature\Comunicacao;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\CommunicationType;
use App\Enums\ViabilityRequestStatus;
use App\Models\Activity;
use App\Models\AnalysisPendency;
use App\Models\Communication;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Notifications\PendenciaExpiradaNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * HU-091 RN-005 (pendencias:expirar): a pendência aberta com due_at vencido sem
 * resposta é marcada como Expirada e o analista responsável é notificado — SEM
 * decisão automática. O rito de não-resposta (indeferir por prazo) é pendência
 * SEDUR e NÃO é inventado: hoje a rotina expira + notifica + mantém o estado do
 * processo. Auditada (RN-002) e idempotente: a própria transição Aberta→Expirada
 * impede o reprocesso (a 2ª passada não acha mais Aberta vencida).
 */
class ExpirarPendenciasCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function processoComAnalista(User $analista): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmPendencia,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ]);

        $request->forceFill(['assigned_user_id' => $analista->id])->save();

        return $request;
    }

    public function test_pendencia_vencida_vira_expirada_notifica_o_analista_e_audita_sem_decisao_automatica(): void
    {
        NotificationFacade::fake();
        $analista = User::factory()->analista()->create();
        $processo = $this->processoComAnalista($analista);
        $pendencia = AnalysisPendency::factory()->create([
            'viability_request_id' => $processo->id,
            'requested_by_user_id' => $analista->id,
            'status' => AnalysisPendencyStatus::Aberta,
            'due_at' => now()->subDay(), // vencida sem resposta
        ]);

        $this->artisan('pendencias:expirar')->assertSuccessful();

        $this->assertSame(AnalysisPendencyStatus::Expirada, $pendencia->refresh()->status);

        // Anti-fachada: o processo NÃO é decidido/transicionado por não-resposta.
        $this->assertSame(ViabilityRequestStatus::EmPendencia, $processo->refresh()->status);

        $this->assertDatabaseHas('communications', [
            'viability_request_id' => $processo->id,
            'recipient_user_id' => $analista->id,
            'type' => CommunicationType::PendenciaExpirada->value,
        ]);
        NotificationFacade::assertSentTo($analista, PendenciaExpiradaNotification::class);

        // Auditoria da expiração (RN-002).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'pendencia-expirada',
            'result' => 'expirada',
        ]);
    }

    public function test_pendencia_dentro_do_prazo_permanece_intacta(): void
    {
        NotificationFacade::fake();
        $analista = User::factory()->analista()->create();
        $processo = $this->processoComAnalista($analista);
        $pendencia = AnalysisPendency::factory()->create([
            'viability_request_id' => $processo->id,
            'requested_by_user_id' => $analista->id,
            'status' => AnalysisPendencyStatus::Aberta,
            'due_at' => now()->addDays(5),
        ]);

        $this->artisan('pendencias:expirar')->assertSuccessful();

        $this->assertSame(AnalysisPendencyStatus::Aberta, $pendencia->refresh()->status);
        $this->assertSame(0, Communication::query()->count());
        NotificationFacade::assertNothingSent();
    }

    public function test_rodar_duas_vezes_nao_reprocessa_nem_duplica(): void
    {
        NotificationFacade::fake();
        $analista = User::factory()->analista()->create();
        $processo = $this->processoComAnalista($analista);
        AnalysisPendency::factory()->create([
            'viability_request_id' => $processo->id,
            'requested_by_user_id' => $analista->id,
            'status' => AnalysisPendencyStatus::Aberta,
            'due_at' => now()->subDay(),
        ]);

        $this->artisan('pendencias:expirar')->assertSuccessful();
        $comunicacoesAposPrimeira = Communication::query()->count();
        $auditoriasAposPrimeira = Activity::query()->where('event', 'pendencia-expirada')->count();

        $this->artisan('pendencias:expirar')->assertSuccessful();

        $this->assertGreaterThan(0, $comunicacoesAposPrimeira);
        $this->assertSame($comunicacoesAposPrimeira, Communication::query()->count(), 'A 2ª execução não pode duplicar communications.');
        $this->assertSame($auditoriasAposPrimeira, Activity::query()->where('event', 'pendencia-expirada')->count(), 'A 2ª execução não pode reprocessar a expiração.');
    }

    public function test_no_op_honesto_quando_nao_ha_pendencias_vencidas(): void
    {
        NotificationFacade::fake();
        $analista = User::factory()->analista()->create();
        $processo = $this->processoComAnalista($analista);
        AnalysisPendency::factory()->respondida()->create([
            'viability_request_id' => $processo->id,
            'requested_by_user_id' => $analista->id,
        ]);

        $this->artisan('pendencias:expirar')->assertSuccessful();

        $this->assertSame(0, Communication::query()->count());
        $this->assertSame(0, Activity::query()->where('event', 'pendencia-expirada')->count());
        NotificationFacade::assertNothingSent();
    }
}
