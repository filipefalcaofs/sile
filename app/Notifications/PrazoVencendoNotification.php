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
 * Aviso ANTECIPADO de prazo próximo do vencimento (HU-093) — para o analista
 * (prazo de análise, analysis_due_at) ou para o requerente (prazo de pendência,
 * analysis_pendencies.due_at). É uma comunicação de PROCESSO: implementa
 * ProcessNotification para percorrer o NotificationDispatcher (multicanal +
 * ledger communications) e o via() LÊ os canais congelados no disparo (espelha o
 * padrão do EP11, sem reresolver toggles no worker).
 *
 * SÓ avisa: não transiciona o processo nem grava timeline (a fonte de prazo é
 * ÚNICA — analysis_due_at — para não brigar com a HU-129). O conteúdo (assunto/
 * detalhe/url) é montado pelo comando; o destino do WhatsApp é resolvido pelo
 * WhatsAppChannel a partir do notifiable.
 */
class PrazoVencendoNotification extends Notification implements ProcessNotification, ShouldQueue
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
        public readonly string $assunto,
        public readonly string $detalhe,
        public readonly string $url,
    ) {}

    public function viabilityRequestId(): ?int
    {
        return $this->viabilityRequestId;
    }

    public function communicationType(): CommunicationType
    {
        return CommunicationType::PrazoVencendo;
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
            ->subject($this->assunto)
            ->greeting('Olá!')
            ->line($this->detalhe)
            ->action('Abrir no SILE', $this->url)
            ->line('Este é um aviso automático — não responda a esta mensagem.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo' => CommunicationType::PrazoVencendo->value,
            'viability_request_id' => $this->viabilityRequestId,
            'protocolo' => $this->protocolNumber,
            'titulo' => $this->assunto,
            'mensagem' => $this->detalhe,
            'url' => $this->url,
        ];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        return new WhatsAppMessage(
            to: '',
            body: trim($this->assunto.' — '.$this->detalhe),
            meta: ['tipo' => CommunicationType::PrazoVencendo->value, 'protocolo' => $this->protocolNumber],
        );
    }
}
