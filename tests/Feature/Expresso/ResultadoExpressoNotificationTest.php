<?php

namespace Tests\Feature\Expresso;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationType;
use App\Enums\DecisionOutcome;
use App\Models\Parameter;
use App\Models\User;
use App\Notifications\Contracts\ProcessNotification;
use App\Notifications\ResultadoExpressoNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notificação de ciência do resultado do fluxo expresso (HU-077). A partir do
 * EP11 (11-06) ela é uma ProcessNotification multicanal: percorre o
 * NotificationDispatcher (ganha in-app + histórico em communications) — o via()
 * lê os canais CONGELADOS no disparo (fallback ['mail'] fora do dispatcher) e o
 * toDatabase() alimenta a central in-app. O e-mail (toMail) mantém o assunto
 * parametrizável por desfecho (deferida usa expresso.notificacao.assunto_deferida,
 * indeferida usa _indeferida — RN-005), corpo pt-BR com o número de protocolo e
 * SEM anexo de TVL (RN-004): o canal oficial de entrega do documento é o
 * Regin/SEFAZ. Enfileirável (ShouldQueue) como as demais notificações da casa.
 */
class ResultadoExpressoNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function notifiable(): User
    {
        return User::factory()->make();
    }

    public function test_e_enfileiravel(): void
    {
        $this->assertInstanceOf(
            ShouldQueue::class,
            new ResultadoExpressoNotification('VIA-2026-000001', DecisionOutcome::Deferida),
        );
    }

    public function test_implementa_o_contrato_de_processo_do_tipo_resultado(): void
    {
        $notification = new ResultadoExpressoNotification('VIA-2026-000001', DecisionOutcome::Deferida, 42);

        $this->assertInstanceOf(ProcessNotification::class, $notification);
        $this->assertSame(CommunicationType::Resultado, $notification->communicationType());
        $this->assertSame(42, $notification->viabilityRequestId());
    }

    public function test_via_usa_o_email_como_fallback_sem_canais_congelados(): void
    {
        // Fora do dispatcher (sem canais congelados), o via() ainda entrega por
        // e-mail — o canal histórico do aviso de resultado.
        $notification = new ResultadoExpressoNotification('VIA-2026-000001', DecisionOutcome::Deferida);

        $this->assertSame(['mail'], $notification->via($this->notifiable()));
    }

    public function test_via_e_dinamico_e_le_os_canais_congelados_pelo_dispatcher(): void
    {
        // No envio enfileirado o via() LÊ os canais congelados pelo dispatcher
        // (sem reresolver toggles) e traduz cada CommunicationChannel para o
        // driver nativo (Email => mail, InApp => database).
        $notification = new ResultadoExpressoNotification('VIA-2026-000001', DecisionOutcome::Deferida, 42);
        $notification->freezeChannels([CommunicationChannel::Email, CommunicationChannel::InApp]);

        $this->assertSame(['mail', 'database'], $notification->via($this->notifiable()));

        $notification->freezeChannels([CommunicationChannel::InApp]);

        $this->assertSame(['database'], $notification->via($this->notifiable()));
    }

    public function test_assunto_de_deferimento_vem_do_parametro(): void
    {
        $mail = (new ResultadoExpressoNotification('VIA-2026-000001', DecisionOutcome::Deferida))
            ->toMail($this->notifiable());

        $this->assertSame('Resultado da sua solicitação de viabilidade: deferida', $mail->subject);
    }

    public function test_assunto_de_indeferimento_vem_do_parametro(): void
    {
        $mail = (new ResultadoExpressoNotification('VIA-2026-000002', DecisionOutcome::Indeferida))
            ->toMail($this->notifiable());

        $this->assertSame('Resultado da sua solicitação de viabilidade: indeferida', $mail->subject);
    }

    public function test_assunto_respeita_o_override_administravel(): void
    {
        // Parameter::saved invalida o cache da chave (efeito sem deploy, HU-014):
        // o assunto reflete o valor administrado pelo gestor, sem novo deploy.
        Parameter::query()->create([
            'key' => 'expresso.notificacao.assunto_deferida',
            'group' => 'expresso',
            'type' => 'string',
            'value' => 'Sua viabilidade foi aprovada',
            'default_value' => 'Resultado da sua solicitação de viabilidade: deferida',
            'validation_rules' => ['required', 'string', 'max:150'],
            'description' => 'Assunto do e-mail de resultado deferido.',
        ]);

        $mail = (new ResultadoExpressoNotification('VIA-2026-000001', DecisionOutcome::Deferida))
            ->toMail($this->notifiable());

        $this->assertSame('Sua viabilidade foi aprovada', $mail->subject);
    }

    public function test_informa_o_numero_de_protocolo_no_corpo(): void
    {
        $mail = (new ResultadoExpressoNotification('VIA-2026-000777', DecisionOutcome::Deferida))
            ->toMail($this->notifiable());

        $this->assertTrue(
            collect($mail->introLines)->contains(fn (string $line) => str_contains($line, 'VIA-2026-000777')),
            'O corpo do e-mail deve informar o número de protocolo.',
        );
    }

    public function test_nao_anexa_o_tvl(): void
    {
        // RN-004: o documento oficial (TVL) é entregue pelo Regin/SEFAZ, nunca
        // por e-mail. A notificação não pode carregar anexo de TVL/PDF.
        $mail = (new ResultadoExpressoNotification('VIA-2026-000001', DecisionOutcome::Deferida))
            ->toMail($this->notifiable());

        $this->assertSame([], $mail->attachments);
        $this->assertSame([], $mail->rawAttachments);
    }

    public function test_to_database_traz_o_desfecho_e_o_link_de_acompanhamento(): void
    {
        // In-app (HU-096): a central exibe o desfecho e leva o cidadão ao portal
        // para acompanhar a solicitação.
        $payload = (new ResultadoExpressoNotification('VIA-2026-000777', DecisionOutcome::Indeferida, 777))
            ->toDatabase($this->notifiable());

        $this->assertSame(CommunicationType::Resultado->value, $payload['type']);
        $this->assertSame('VIA-2026-000777', $payload['protocol_number']);
        $this->assertSame(DecisionOutcome::Indeferida->value, $payload['outcome']);
        $this->assertStringContainsString('indeferida', $payload['title']);
        $this->assertStringContainsString('VIA-2026-000777', $payload['message']);
        $this->assertStringContainsString('/solicitacoes/777', $payload['url']);
    }
}
