<?php

namespace App\Notifications;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationType;
use App\Notifications\Contracts\ProcessNotification;
use App\Services\Whatsapp\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso ao analista responsável de que um convite expirou sem resposta do
 * requerente e o processo foi INDEFERIDO automaticamente (relatório SEDUR
 * 2026-07-09, Fase 2a). É comunicação de PROCESSO: implementa ProcessNotification
 * para percorrer o NotificationDispatcher (multicanal + ledger).
 */
class PendenciaExpiradaNotification extends Notification implements ProcessNotification, ShouldQueue
{
    use Queueable;

    /**
     * Mapa {driver→communicationId} congelado pelo dispatcher (espelha o
     * emailLogId único): referência da linha do ledger lida no envio.
     *
     * @var array<string, int>
     */
    public array $communicationIds = [];

    /** @var array<int, CommunicationChannel> */
    private array $frozenChannels = [];

    public function __construct(
        private readonly int $viabilityRequestId,
        public readonly string $protocolNumber,
        public readonly string $descricao,
        public readonly string $url,
    ) {}

    public function viabilityRequestId(): ?int
    {
        return $this->viabilityRequestId;
    }

    public function communicationType(): CommunicationType
    {
        return CommunicationType::PendenciaExpirada;
    }

    /**
     * @param  array<int, CommunicationChannel>  $channels
     */
    public function freezeChannels(array $channels): void
    {
        $this->frozenChannels = $channels;
    }

    /**
     * @return array<int, CommunicationChannel>
     */
    public function channels(): array
    {
        return $this->frozenChannels;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return array_map(fn (CommunicationChannel $channel): string => match ($channel) {
            CommunicationChannel::Email => 'mail',
            CommunicationChannel::InApp => 'database',
            CommunicationChannel::Whatsapp => 'whatsapp',
        }, $this->channels());
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Convite expirado — processo indeferido — {$this->protocolNumber}")
            ->greeting('Olá!')
            ->line("O convite da solicitação {$this->protocolNumber} expirou sem resposta do requerente dentro do prazo, e o processo foi indeferido automaticamente.")
            ->line("Convite: {$this->descricao}")
            ->action('Abrir o processo no SILE', $this->url)
            ->line('Este é um aviso automático — não responda a esta mensagem.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo' => CommunicationType::PendenciaExpirada->value,
            'viability_request_id' => $this->viabilityRequestId,
            'protocolo' => $this->protocolNumber,
            'titulo' => "Convite expirado — processo indeferido — {$this->protocolNumber}",
            'mensagem' => "O convite \"{$this->descricao}\" expirou sem resposta do requerente; o processo foi indeferido automaticamente.",
            'url' => $this->url,
        ];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        return new WhatsAppMessage(
            to: '',
            body: "Convite expirado sem resposta na solicitação {$this->protocolNumber} — processo indeferido.",
            meta: ['tipo' => CommunicationType::PendenciaExpirada->value, 'protocolo' => $this->protocolNumber],
        );
    }
}
