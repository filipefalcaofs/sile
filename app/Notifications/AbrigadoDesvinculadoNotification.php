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
 * Aviso ao requerente de um ABRIGADO de que a SEDE de escritório virtual saiu
 * da inscrição (RN-EV-06 / SEDUR 2026-07-16): o abrigado é DESVINCULADO da sede
 * e notificado — sem cassação automática (o requerente deve regularizar). É
 * comunicação de PROCESSO: percorre o NotificationDispatcher (multicanal + ledger).
 *
 * Reusa CommunicationType::Resultado (aviso de desfecho ao requerente) — não se
 * criou um tipo novo (o enum é fechado por decisão de projeto); se a SEDUR
 * quiser um tipo dedicado, é um follow-up.
 */
class AbrigadoDesvinculadoNotification extends Notification implements ProcessNotification, ShouldQueue
{
    use Queueable;

    /** @var array<string, int> */
    public array $communicationIds = [];

    /** @var array<int, CommunicationChannel> */
    private array $frozenChannels = [];

    public function __construct(
        private readonly int $viabilityRequestId,
        public readonly string $protocolNumber,
        public readonly string $propertyRegistration,
        public readonly string $url,
    ) {}

    public function viabilityRequestId(): ?int
    {
        return $this->viabilityRequestId;
    }

    public function communicationType(): CommunicationType
    {
        return CommunicationType::Resultado;
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
            ->subject("Escritório virtual desvinculado — solicitação {$this->protocolNumber}")
            ->greeting('Olá!')
            ->line("A sede de escritório virtual da inscrição imobiliária {$this->propertyRegistration} deixou de estar vinculada a este endereço.")
            ->line("Por isso, a sua solicitação {$this->protocolNumber} foi desvinculada da sede. Regularize a situação do seu endereço junto à SEDUR.")
            ->action('Abrir o processo no SILE', $this->url)
            ->line('Este é um aviso automático — não responda a esta mensagem.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo' => CommunicationType::Resultado->value,
            'viability_request_id' => $this->viabilityRequestId,
            'protocolo' => $this->protocolNumber,
            'titulo' => "Escritório virtual desvinculado — {$this->protocolNumber}",
            'mensagem' => "A sede da inscrição {$this->propertyRegistration} foi desvinculada; sua solicitação {$this->protocolNumber} deixou de estar vinculada à sede.",
            'url' => $this->url,
        ];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        return new WhatsAppMessage(
            to: '',
            body: "A sede de escritório virtual da inscrição {$this->propertyRegistration} foi desvinculada; sua solicitação {$this->protocolNumber} perdeu o vínculo com a sede.",
            meta: ['tipo' => CommunicationType::Resultado->value, 'protocolo' => $this->protocolNumber],
        );
    }
}
