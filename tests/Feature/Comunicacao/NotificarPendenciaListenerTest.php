<?php

namespace Tests\Feature\Comunicacao;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Enums\CommunicationType;
use App\Events\PendenciaSolicitada;
use App\Listeners\NotificarPendencia;
use App\Models\AnalysisPendency;
use App\Models\Communication;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Notifications\PendenciaSolicitadaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * NotificarPendencia (HU-090): listener AUTO-DESCOBERTO do PendenciaSolicitada
 * que generaliza o aviso de pendência para MULTICANAL via o NotificationDispatcher
 * (11-04) — o requerente passa a receber e-mail + in-app + histórico (HU-096),
 * em vez do e-mail direto da Fase 10.
 *
 * Registro ÚNICO por auto-descoberta (type-hint do evento no handle; NUNCA
 * Event::listen — lição Fases 8/9), travado por CONTAGEM. Degradação HONESTA:
 * sem requerente → audita 'sem-destinatario' e não envia; WhatsApp off (default,
 * 11-02) → linha 'desativado' (nunca "enviado" fictício).
 */
class NotificarPendenciaListenerTest extends TestCase
{
    use RefreshDatabase;

    private function pendenciaAberta(ViabilityRequest $request): AnalysisPendency
    {
        return AnalysisPendency::factory()->create([
            'viability_request_id' => $request->id,
            'status' => AnalysisPendencyStatus::Aberta,
        ]);
    }

    public function test_evento_notifica_o_requerente_uma_unica_vez_e_multicanal(): void
    {
        Notification::fake();

        $requester = User::factory()->create();
        $request = ViabilityRequest::factory()->protocoled()->create(['requester_user_id' => $requester->id]);
        $pendency = $this->pendenciaAberta($request);

        event(new PendenciaSolicitada($request, $pendency));

        // CONTAGEM (auto-descoberta, sem Event::listen): o requerente é notificado
        // EXATAMENTE 1× — um listener duplicado quebraria a contagem.
        Notification::assertSentToTimes($requester, PendenciaSolicitadaNotification::class, 1);

        // Multicanal pelo mapa default (pendencia_aberta = [email, in_app]): o
        // ledger nasce na_fila por canal habilitado (HU-096).
        $this->assertDatabaseHas('communications', [
            'viability_request_id' => $request->id,
            'recipient_user_id' => $requester->id,
            'type' => CommunicationType::PendenciaAberta->value,
            'channel' => CommunicationChannel::Email->value,
            'status' => CommunicationStatus::NaFila->value,
        ]);
        $this->assertDatabaseHas('communications', [
            'viability_request_id' => $request->id,
            'recipient_user_id' => $requester->id,
            'type' => CommunicationType::PendenciaAberta->value,
            'channel' => CommunicationChannel::InApp->value,
            'status' => CommunicationStatus::NaFila->value,
        ]);
    }

    public function test_exatamente_um_listener_auto_descoberto_trata_pendencia_solicitada(): void
    {
        // Lição Fases 8/9: o NotificarPendencia é o ÚNICO listener auto-descoberto
        // de PendenciaSolicitada. Um Event::listen adicional duplicaria o aviso.
        $this->assertCount(1, Event::getListeners(PendenciaSolicitada::class));
    }

    public function test_handle_faz_type_hint_do_evento(): void
    {
        // Contrato da auto-descoberta: o handle recebe o evento por type-hint
        // (base do registro automático — sem Event::listen).
        $params = (new \ReflectionMethod(NotificarPendencia::class, 'handle'))->getParameters();

        $this->assertSame(PendenciaSolicitada::class, $params[0]->getType()?->getName());
    }

    public function test_sem_requerente_audita_e_nao_notifica(): void
    {
        Notification::fake();

        $request = ViabilityRequest::factory()->protocoled()->create();
        $pendency = $this->pendenciaAberta($request);
        // Sem destinatário: o processo não tem requerente carregável.
        $request->setRelation('requester', null);

        event(new PendenciaSolicitada($request, $pendency));

        // Honesto: sem destinatário não há envio nem ledger — só a auditoria da
        // tentativa (nunca falha silenciosa).
        Notification::assertNothingSent();
        $this->assertSame(0, Communication::query()->count());
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'notificacoes',
            'event' => 'pendencia-aberta',
            'result' => 'sem-destinatario',
            'subject_type' => $request->getMorphClass(),
            'subject_id' => $request->id,
        ]);
    }

    public function test_whatsapp_no_mapa_off_degrada_para_desativado_sem_fingir_envio(): void
    {
        Notification::fake();

        // Mapa custom inclui whatsapp (toggle off por default — 11-02).
        config()->set('sile.notificacoes.mapa_canais', [
            'pendencia_aberta' => ['email', 'in_app', 'whatsapp'],
        ]);

        $requester = User::factory()->create();
        $request = ViabilityRequest::factory()->protocoled()->create(['requester_user_id' => $requester->id]);
        $pendency = $this->pendenciaAberta($request);

        event(new PendenciaSolicitada($request, $pendency));

        // HU-095: whatsapp degrada para 'desativado' — nunca um "enviado" fictício.
        $this->assertDatabaseHas('communications', [
            'viability_request_id' => $request->id,
            'channel' => CommunicationChannel::Whatsapp->value,
            'status' => CommunicationStatus::Desativado->value,
        ]);
        $this->assertSame(0, Communication::query()
            ->where('channel', CommunicationChannel::Whatsapp->value)
            ->where('status', CommunicationStatus::Enviado->value)
            ->count());

        // E-mail + in-app seguem reais (na_fila).
        $this->assertDatabaseHas('communications', [
            'channel' => CommunicationChannel::Email->value,
            'status' => CommunicationStatus::NaFila->value,
        ]);
        $this->assertDatabaseHas('communications', [
            'channel' => CommunicationChannel::InApp->value,
            'status' => CommunicationStatus::NaFila->value,
        ]);
    }
}
