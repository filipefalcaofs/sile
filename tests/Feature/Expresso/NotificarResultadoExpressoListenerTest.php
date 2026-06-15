<?php

namespace Tests\Feature\Expresso;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Enums\CommunicationType;
use App\Enums\DecisionOutcome;
use App\Events\ResultadoEmitido;
use App\Models\Parameter;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Notifications\ResultadoExpressoNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * NotificarResultadoExpresso — listener AUTO-DESCOBERTO do ResultadoEmitido
 * (HU-077). A partir do EP11 (11-06) o aviso percorre o NotificationDispatcher
 * (ganha in-app + histórico communications, HU-096) em vez do e-mail direto.
 * Registro ÚNICO por auto-descoberta — notifica EXATAMENTE 1× (lição Fase 8:
 * Event::listen duplicaria). O toggle de TIPO features.notificacao_resultado_expresso
 * off → NÃO notifica e AUDITA a degradação (nunca falha silenciosa); os CANAIS
 * passam a ser resolvidos pelo dispatcher (mapa ∩ toggles). Sem destinatário com
 * e-mail → audita e não notifica.
 */
class NotificarResultadoExpressoListenerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: ViabilityRequest, 1: ViabilityDecision}
     */
    private function decisao(DecisionOutcome $outcome = DecisionOutcome::Deferida, ?User $requester = null): array
    {
        $request = ViabilityRequest::factory()->protocoled()->create(
            $requester !== null ? ['requester_user_id' => $requester->id] : []
        );

        $factory = $outcome === DecisionOutcome::Indeferida
            ? ViabilityDecision::factory()->indeferida()
            : ViabilityDecision::factory();

        $decision = $factory->create(['viability_request_id' => $request->id]);

        return [$request, $decision];
    }

    public function test_notifica_o_requerente_uma_unica_vez(): void
    {
        Notification::fake();

        [$request, $decision] = $this->decisao();

        event(new ResultadoEmitido($request, $decision));

        // CONTAGEM: a auto-descoberta registra o listener uma ÚNICA vez — sem
        // duplicar a notificação (lição Fase 8).
        Notification::assertSentToTimes($request->requester, ResultadoExpressoNotification::class, 1);

        // Anti-fachada: o aviso percorre o NotificationDispatcher e ganha o ledger
        // communications (in-app + histórico HU-096) — não mais email_logs. O mapa
        // default de 'resultado' = [email, in_app] → duas linhas na_fila.
        $this->assertDatabaseHas('communications', [
            'viability_request_id' => $request->id,
            'recipient_user_id' => $request->requester->id,
            'channel' => CommunicationChannel::Email->value,
            'type' => CommunicationType::Resultado->value,
            'status' => CommunicationStatus::NaFila->value,
        ]);
        $this->assertDatabaseHas('communications', [
            'viability_request_id' => $request->id,
            'channel' => CommunicationChannel::InApp->value,
            'type' => CommunicationType::Resultado->value,
            'status' => CommunicationStatus::NaFila->value,
        ]);
    }

    public function test_toggle_desligado_nao_envia_e_audita_a_degradacao(): void
    {
        Notification::fake();

        // Parameter::saved invalida o cache da chave (efeito sem deploy, HU-014).
        Parameter::query()->create([
            'key' => 'features.notificacao_resultado_expresso',
            'group' => 'features',
            'type' => 'boolean',
            'value' => '0',
            'default_value' => '1',
            'validation_rules' => ['required', 'boolean'],
            'description' => 'Toggle do e-mail de resultado do fluxo expresso.',
        ]);

        [$request, $decision] = $this->decisao();

        event(new ResultadoEmitido($request, $decision));

        // Degradação COMUNICADA: não envia, mas deixa trilha (nunca silenciosa).
        Notification::assertNothingSent();
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'notificacoes',
            'event' => 'resultado-expresso',
            'result' => 'desativado',
            'subject_type' => $request->getMorphClass(),
            'subject_id' => $request->id,
        ]);
    }

    public function test_indeferimento_usa_o_assunto_de_indeferida(): void
    {
        Notification::fake();

        [$request, $decision] = $this->decisao(DecisionOutcome::Indeferida);

        event(new ResultadoEmitido($request, $decision));

        Notification::assertSentTo(
            $request->requester,
            ResultadoExpressoNotification::class,
            fn (ResultadoExpressoNotification $notification): bool => $notification->outcome === DecisionOutcome::Indeferida
                && $notification->toMail($request->requester)->subject === 'Resultado da sua solicitação de viabilidade: indeferida',
        );
    }

    public function test_sem_destinatario_com_email_audita_e_nao_envia(): void
    {
        Notification::fake();

        $semEmail = User::factory()->create(['email' => '']);
        [$request, $decision] = $this->decisao(requester: $semEmail);

        event(new ResultadoEmitido($request, $decision));

        // Honesto: sem e-mail válido não há envio — mas a tentativa é auditada.
        Notification::assertNothingSent();
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'notificacoes',
            'event' => 'resultado-expresso',
            'result' => 'sem-destinatario',
            'subject_type' => $request->getMorphClass(),
            'subject_id' => $request->id,
        ]);
    }
}
