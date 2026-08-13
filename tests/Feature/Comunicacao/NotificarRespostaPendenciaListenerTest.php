<?php

namespace Tests\Feature\Comunicacao;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Enums\CommunicationType;
use App\Enums\ViabilityRequestStatus;
use App\Events\PendenciaRespondida;
use App\Listeners\NotificarRespostaPendencia;
use App\Models\AnalysisPendency;
use App\Models\Communication;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Notifications\RespostaPendenciaNotification;
use App\Services\Analise\PendenciaService;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * NotificarRespostaPendencia (HU-091/092): fecha o ciclo que a Fase 10 não
 * fechava — quando o requerente responde a pendência (em_pendencia→em_analise),
 * o ANALISTA responsável (assigned_user_id) é notificado de que a análise
 * reabriu, pelo NotificationDispatcher (multicanal).
 *
 * O gancho é o evento after-commit PendenciaRespondida (espelha PendenciaSolicitada/
 * ResultadoEmitido): só efeitos de uma resposta efetivada. O listener é ÚNICO por
 * AUTO-DESCOBERTA (type-hint do evento no handle; NUNCA Event::listen — lição
 * Fases 8/9), travado por CONTAGEM. Degradação HONESTA: sem analista atribuído,
 * audita 'sem-destinatario' e não envia.
 */
class NotificarRespostaPendenciaListenerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Processo em em_pendencia com pendência aberta, opcionalmente com analista
     * responsável atribuído. Retorna [request, pendency, requester].
     *
     * @return array{0: ViabilityRequest, 1: AnalysisPendency, 2: User}
     */
    private function pendenciaParaResponder(?User $analista = null): array
    {
        $requester = User::factory()->create();
        $request = ViabilityRequest::factory()->protocoled()->create(['requester_user_id' => $requester->id]);
        $request->forceFill([
            'status' => ViabilityRequestStatus::EmPendencia,
            'assigned_user_id' => $analista?->id,
        ])->save();

        $pendency = AnalysisPendency::factory()->create([
            'viability_request_id' => $request->id,
            'status' => AnalysisPendencyStatus::Aberta,
        ]);

        return [$request->refresh(), $pendency, $requester];
    }

    public function test_resposta_notifica_o_analista_responsavel_uma_unica_vez(): void
    {
        Notification::fake();

        $analista = User::factory()->create();
        [$request, $pendency, $requester] = $this->pendenciaParaResponder($analista);

        // Caminho REAL: o requerente responde pelo serviço → após o commit dispara
        // PendenciaRespondida → o listener notifica o analista.
        $this->actingAs($requester);
        app(PendenciaService::class)->responder($pendency, 'Documentos anexados no portal.');

        // CONTAGEM (auto-descoberta): o analista é notificado EXATAMENTE 1×.
        Notification::assertSentToTimes($analista, RespostaPendenciaNotification::class, 1);

        // Ledger honesto: pendencia_respondida (mapa default = [in_app]).
        $this->assertDatabaseHas('communications', [
            'viability_request_id' => $request->id,
            'recipient_user_id' => $analista->id,
            'type' => CommunicationType::PendenciaRespondida->value,
            'channel' => CommunicationChannel::InApp->value,
            'status' => CommunicationStatus::NaFila->value,
        ]);

        // O requerente NÃO é notificado (o aviso de resposta é para o analista).
        Notification::assertNotSentTo($requester, RespostaPendenciaNotification::class);
    }

    public function test_o_evento_pendencia_respondida_e_after_commit(): void
    {
        // Espelha PendenciaSolicitada/ResultadoEmitido: o contrato after-commit
        // garante que efeitos só são observados quando a transação efetiva (em
        // rollback nada dispara). Sob RefreshDatabase os eventos de domínio
        // disparam síncronos, então o contrato é verificado estruturalmente.
        [$request, $pendency] = $this->pendenciaParaResponder();

        $this->assertInstanceOf(
            ShouldDispatchAfterCommit::class,
            new PendenciaRespondida($request, $pendency),
        );
    }

    public function test_exatamente_um_listener_auto_descoberto_trata_pendencia_respondida(): void
    {
        // Lição Fases 8/9: o NotificarRespostaPendencia é o ÚNICO listener
        // auto-descoberto de PendenciaRespondida. Um Event::listen duplicaria o aviso.
        $this->assertCount(1, Event::getListeners(PendenciaRespondida::class));
    }

    public function test_handle_faz_type_hint_do_evento(): void
    {
        $params = (new \ReflectionMethod(NotificarRespostaPendencia::class, 'handle'))->getParameters();

        $this->assertSame(PendenciaRespondida::class, $params[0]->getType()?->getName());
    }

    public function test_sem_analista_responsavel_audita_e_nao_notifica(): void
    {
        Notification::fake();

        // Processo sem analista atribuído (assigned_user_id null).
        [$request, $pendency] = $this->pendenciaParaResponder();

        event(new PendenciaRespondida($request, $pendency));

        // Honesto: sem destinatário não há envio nem ledger — só a auditoria.
        Notification::assertNothingSent();
        $this->assertSame(0, Communication::query()->count());
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'notificacoes',
            'event' => 'pendencia-respondida',
            'result' => 'sem-destinatario',
            'subject_type' => $request->getMorphClass(),
            'subject_id' => $request->id,
        ]);
    }
}
