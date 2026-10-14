<?php

namespace App\Notifications;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationType;
use App\Notifications\Contracts\ProcessNotification;
use App\Notifications\Messages\EmailViabiliza;
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
        return (new EmailViabiliza)
            ->subject("Escritório virtual desvinculado — solicitação {$this->protocolNumber}")
            ->selo('Regularização necessária', 'alerta')
            ->titulo('Sua empresa foi desvinculada da Sede de Escritório Virtual')
            ->greeting('Olá!')
            ->line('A Sede de Escritório Virtual à qual sua empresa está vinculada deixou de prestar o serviço de Sede neste endereço.')
            ->destaque('Para regularização do cadastro, deverá ser solicitada a alteração de endereço da empresa.', 'alerta')
            ->dados([['Solicitação', $this->protocolNumber]])
            ->action('Abrir o processo no Viabiliza', $this->url);
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
            'mensagem' => 'A Sede de Escritório Virtual à qual sua empresa está vinculada deixou de prestar o serviço de Sede neste endereço. Para regularização do cadastro, deverá ser solicitada a alteração de endereço da empresa.',
            'url' => $this->url,
        ];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        return new WhatsAppMessage(
            to: '',
            body: 'A Sede de Escritório Virtual à qual sua empresa está vinculada deixou de prestar o serviço de Sede neste endereço. Para regularização do cadastro, deverá ser solicitada a alteração de endereço da empresa.',
            meta: ['tipo' => CommunicationType::Resultado->value, 'protocolo' => $this->protocolNumber],
        );
    }
}
