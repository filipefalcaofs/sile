<?php

namespace App\Notifications;

use App\Enums\DecisionOutcome;
use App\Models\EmailLog;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail complementar de ciência ao requerente do resultado do fluxo expresso
 * (HU-077). Espelha o padrão VerifyEmailQueued/EmailLog: ShouldQueue + emailLogId
 * (o LogNotificationSent marca como enviado quando o NotificationSent dispara;
 * failed() marca a falha). O EmailLog é criado no DISPARO (NotificarResultadoExpresso),
 * que injeta o emailLogId aqui — idêntico ao fluxo de verificação de e-mail.
 *
 * NÃO anexa o TVL/PDF (RN-004): o canal oficial de entrega do documento de
 * viabilidade ao cidadão é o Regin/SEFAZ (decisão SEDUR). Este e-mail é apenas o
 * aviso do desfecho, com assunto/texto parametrizáveis pela HU-014
 * (expresso.notificacao.assunto_deferida / _indeferida) — por isso este arquivo
 * NÃO anexa qualquer documento (nenhuma chamada de anexação no toMail).
 */
class ResultadoExpressoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public ?int $emailLogId = null;

    public function __construct(
        public string $protocolNumber,
        public DecisionOutcome $outcome,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deferida = $this->outcome === DecisionOutcome::Deferida;

        $subject = $deferida
            ? Settings::get('expresso.notificacao.assunto_deferida', config('sile.expresso.notificacao.assunto_deferida'))
            : Settings::get('expresso.notificacao.assunto_indeferida', config('sile.expresso.notificacao.assunto_indeferida'));

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Olá!')
            ->line("A análise da sua solicitação de viabilidade de protocolo {$this->protocolNumber} foi concluída.");

        if ($deferida) {
            $message
                ->line('Resultado: deferida.')
                ->line('O documento oficial de viabilidade é disponibilizado pelos canais da Junta Comercial (Regin) e da SEFAZ municipal — este e-mail é apenas um aviso e não acompanha anexo.');
        } else {
            $message
                ->line('Resultado: indeferida.')
                ->line('Acompanhe os detalhes e as orientações pelos canais da Junta Comercial (Regin).');
        }

        return $message->line('Este é um aviso automático — não responda a esta mensagem.');
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->emailLogId) {
            EmailLog::find($this->emailLogId)?->markAsFailed($exception->getMessage());
        }
    }
}
