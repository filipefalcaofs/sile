<?php

namespace Tests\Feature\Expresso;

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
 * (HU-077). Efeito colateral desacoplado: notifica o requerente do desfecho por
 * e-mail (sem anexo de TVL). Registro ÚNICO por auto-descoberta — notifica
 * EXATAMENTE 1× (lição Fase 8: Event::listen duplicaria). Toggle
 * features.notificacao_resultado_expresso off → NÃO envia e AUDITA a degradação
 * (nunca falha silenciosa). Sem destinatário com e-mail → audita e não envia.
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

        // Anti-fachada: o envio passa pela infra real de e-mail — o EmailLog
        // registra o disparo (status na_fila até o worker marcar enviado).
        $this->assertDatabaseHas('email_logs', [
            'recipient_email' => $request->requester->email,
            'notification_class' => ResultadoExpressoNotification::class,
            'status' => 'na_fila',
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
