<?php

namespace Tests\Feature\Expresso;

use App\Enums\DecisionOutcome;
use App\Models\Parameter;
use App\Models\User;
use App\Notifications\ResultadoExpressoNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E-mail de ciência do resultado do fluxo expresso (HU-077): assunto
 * parametrizável por desfecho — deferida usa expresso.notificacao.assunto_deferida,
 * indeferida usa _indeferida (RN-005) —, corpo pt-BR com o número de protocolo e
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

    public function test_usa_o_canal_de_email(): void
    {
        $notification = new ResultadoExpressoNotification('VIA-2026-000001', DecisionOutcome::Deferida);

        $this->assertSame(['mail'], $notification->via($this->notifiable()));
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
}
