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
 * Aviso ao ANALISTA responsável de que o requerente respondeu a pendência e a
 * análise foi reaberta (HU-087/088) — o retorno que a Fase 10 não fechava.
 * Notification de PROCESSO MULTICANAL: implementa {@see ProcessNotification}
 * (tipo PendenciaRespondida) com via() DINÂMICO — os canais são resolvidos pelo
 * NotificationDispatcher no disparo, congelados aqui e traduzidos para os drivers
 * nativos no envio enfileirado (espelha o padrão de 11-04/05).
 *
 * O conteúdo leva o analista ao processo na GESTÃO (gestao.processos.show) para
 * retomar a análise. O destino do WhatsApp é resolvido pelo WhatsAppChannel
 * (routeNotificationForWhatsapp), não aqui.
 */
class RespostaPendenciaNotification extends Notification implements ProcessNotification, ShouldQueue
{
    use Queueable;

    /**
     * Mapa {driver→communicationId} congelado pelo dispatcher: referência da
     * linha do ledger para os listeners de envio.
     *
     * @var array<string, int>
     */
    public array $communicationIds = [];

    /**
     * Canais (CommunicationChannel) congelados no disparo, lidos pelo via().
     *
     * @var array<int, CommunicationChannel>
     */
    public array $frozenChannels = [];

    public function __construct(
        public string $protocolNumber,
        public int $viabilityRequestId,
    ) {}

    public function viabilityRequestId(): ?int
    {
        return $this->viabilityRequestId;
    }

    public function communicationType(): CommunicationType
    {
        return CommunicationType::PendenciaRespondida;
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
     * via() DINÂMICO: traduz os canais congelados para os drivers nativos. Sem
     * canais congelados (envio fora do dispatcher), cai no e-mail.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($this->frozenChannels === []) {
            return ['mail'];
        }

        return array_map(static fn (CommunicationChannel $channel): string => match ($channel) {
            CommunicationChannel::Email => 'mail',
            CommunicationChannel::InApp => 'database',
            CommunicationChannel::Whatsapp => 'whatsapp',
        }, $this->frozenChannels);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('gestao.processos.show', $this->viabilityRequestId);

        return (new EmailViabiliza)
            ->subject("Convite respondido — análise reaberta ({$this->protocolNumber})")
            ->selo('Análise reaberta', 'neutro')
            ->titulo('O requerente respondeu ao convite')
            ->greeting('Olá!')
            ->line("O requerente respondeu o convite da solicitação {$this->protocolNumber} e a análise foi reaberta.")
            ->dados([['Solicitação', $this->protocolNumber]])
            ->action('Abrir o processo', $url)
            ->line('Retome a análise técnica do processo na gestão do Viabiliza.');
    }

    /**
     * Conteúdo do canal in-app (HU-087/092): título, resumo e link da gestão.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => CommunicationType::PendenciaRespondida->value,
            'title' => CommunicationType::PendenciaRespondida->label(),
            'protocol_number' => $this->protocolNumber,
            'summary' => "O requerente respondeu o convite da solicitação {$this->protocolNumber} — análise reaberta.",
            'viability_request_id' => $this->viabilityRequestId,
            'url' => route('gestao.processos.show', $this->viabilityRequestId),
        ];
    }

    /**
     * Conteúdo do canal WhatsApp (HU-091): texto curto com link. O destino (to,
     * E.164) é injetado pelo WhatsAppChannel a partir do notifiable.
     */
    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $url = route('gestao.processos.show', $this->viabilityRequestId);

        return new WhatsAppMessage(
            to: '',
            body: "Viabiliza: o requerente respondeu o convite da solicitação {$this->protocolNumber}. Análise reaberta: {$url}",
        );
    }
}
