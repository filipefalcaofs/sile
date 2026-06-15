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
 * Aviso ao analista responsável de que uma pendência expirou sem resposta do
 * requerente (HU-091 RN-005). É comunicação de PROCESSO: implementa
 * ProcessNotification para percorrer o NotificationDispatcher (multicanal +
 * ledger). SÓ avisa — a rotina de expiração NÃO decide nem transiciona o processo
 * (o rito de indeferir por não-resposta é pendência SEDUR, não inventado).
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
            ->subject("Pendência expirada sem resposta — {$this->protocolNumber}")
            ->greeting('Olá!')
            ->line("A pendência da solicitação {$this->protocolNumber} expirou sem resposta do requerente dentro do prazo.")
            ->line("Pendência: {$this->descricao}")
            ->action('Abrir o processo no SILE', $this->url)
            ->line('Avalie o processo na análise técnica. Este é um aviso automático — não responda a esta mensagem.');
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
            'titulo' => "Pendência expirada sem resposta — {$this->protocolNumber}",
            'mensagem' => "A pendência \"{$this->descricao}\" expirou sem resposta do requerente.",
            'url' => $this->url,
        ];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        return new WhatsAppMessage(
            to: '',
            body: "Pendência expirada sem resposta na solicitação {$this->protocolNumber}.",
            meta: ['tipo' => CommunicationType::PendenciaExpirada->value, 'protocolo' => $this->protocolNumber],
        );
    }
}
