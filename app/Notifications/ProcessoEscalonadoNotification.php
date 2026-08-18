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
 * Escalonamento por SLA (HU-147): alerta o analista (faixa amarela) ou escala ao
 * gestor (faixa vermelha/vencido) sobre um processo parado além do limiar da
 * etapa. O semáforo é lido da FONTE ÚNICA (analysis_due_at via AnalysisSlaService)
 * pelo comando — esta Notification só transporta o conteúdo já decidido.
 *
 * É comunicação de PROCESSO: implementa ProcessNotification para percorrer o
 * NotificationDispatcher (multicanal + ledger). SÓ notifica — não transiciona o
 * processo nem grava timeline (sem decisão automática; o tratamento é só
 * notificação por default, HU-147 RN-003).
 */
class ProcessoEscalonadoNotification extends Notification implements ProcessNotification, ShouldQueue
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
        return CommunicationType::EscalonamentoSla;
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
            ->action('Abrir o processo no SILE', $this->url)
            ->line('Este é um aviso automático — não responda a esta mensagem.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo' => CommunicationType::EscalonamentoSla->value,
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
            meta: ['tipo' => CommunicationType::EscalonamentoSla->value, 'protocolo' => $this->protocolNumber],
        );
    }
}
