<?php

namespace App\Notifications;

use App\Models\EmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail SIMPLES de aviso ao requerente de que a análise técnica abriu uma
 * pendência na sua solicitação (HU-083 — ciclo interno). Espelha o padrão
 * ResultadoExpressoNotification/EmailLog: ShouldQueue + emailLogId (o
 * LogNotificationSent marca enviado quando o NotificationSent dispara; failed()
 * marca a falha). O EmailLog é criado no DISPARO (PendenciaService), que injeta
 * o emailLogId aqui.
 *
 * É um aviso HONESTO e mínimo: informa a pendência e leva o requerente ao portal
 * SILE ("Minhas solicitações") para responder. A comunicação plena (multicanal —
 * WhatsApp/in-app/templates) e o convite via Simplifica/Regin ficam para o EP11;
 * por isso este e-mail NÃO anexa documento (nenhuma chamada de anexação no toMail).
 */
class PendenciaSolicitadaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public ?int $emailLogId = null;

    public function __construct(
        public string $protocolNumber,
        public string $descricao,
        public int $viabilityRequestId,
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
        $url = route('portal.solicitacoes.show', $this->viabilityRequestId);

        return (new MailMessage)
            ->subject("Pendência na sua solicitação de viabilidade {$this->protocolNumber}")
            ->greeting('Olá!')
            ->line("A análise técnica da sua solicitação de viabilidade de protocolo {$this->protocolNumber} registrou uma pendência que precisa da sua resposta.")
            ->line("Pendência: {$this->descricao}")
            ->action('Responder no portal', $url)
            ->line('Acesse o portal SILE, em "Minhas solicitações", para enviar a complementação. Outros canais de aviso serão disponibilizados em breve.')
            ->line('Este é um aviso automático — não responda a esta mensagem.');
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->emailLogId) {
            EmailLog::find($this->emailLogId)?->markAsFailed($exception->getMessage());
        }
    }
}
