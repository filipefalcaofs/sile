<?php

namespace App\Notifications;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationType;
use App\Models\EmailLog;
use App\Notifications\Contracts\ProcessNotification;
use App\Services\Whatsapp\WhatsAppMessage;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso ao requerente de que a análise técnica abriu uma pendência na sua
 * solicitação (HU-090). A partir do EP11 é uma Notification de PROCESSO
 * MULTICANAL: implementa {@see ProcessNotification} (tipo PendenciaAberta) e tem
 * via() DINÂMICO — os canais são resolvidos pelo NotificationDispatcher no
 * disparo, congelados aqui ({@see $frozenChannels}) e traduzidos para os drivers
 * nativos no envio enfileirado (mail/database/whatsapp), espelhando
 * VerifyEmailQueued::freezeUrlFor.
 *
 * O conteúdo é HONESTO e parametrizável (HU-014): o e-mail lê assunto/corpo de
 * notificacoes.pendencia.* (admin edita sem deploy); o in-app traz título/resumo/
 * link; o WhatsApp, texto curto com link. O destino do WhatsApp é resolvido pelo
 * WhatsAppChannel (routeNotificationForWhatsapp), não aqui.
 *
 * O EmailLog/emailLogId permanece como compatibilidade do canal de e-mail da
 * casa (failed() marca a falha quando há log); o ledger de processo é o
 * communications (mapa {driver→communicationId} congelado pelo dispatcher).
 */
class PendenciaSolicitadaNotification extends Notification implements ProcessNotification, ShouldQueue
{
    use Queueable;

    public ?int $emailLogId = null;

    /**
     * Mapa {driver→communicationId} congelado pelo dispatcher (espelha o
     * emailLogId único): referência da linha do ledger para os listeners de envio.
     *
     * @var array<string, int>
     */
    public array $communicationIds = [];

    /**
     * Canais (CommunicationChannel) resolvidos e congelados no disparo, lidos
     * pelo via() no envio enfileirado.
     *
     * @var array<int, CommunicationChannel>
     */
    public array $frozenChannels = [];

    public function __construct(
        public string $protocolNumber,
        public string $descricao,
        public int $viabilityRequestId,
    ) {}

    public function viabilityRequestId(): ?int
    {
        return $this->viabilityRequestId;
    }

    public function communicationType(): CommunicationType
    {
        return CommunicationType::PendenciaAberta;
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
     * via() DINÂMICO: traduz os canais congelados pelo dispatcher para os drivers
     * nativos. Sem canais congelados (envio fora do dispatcher), cai no e-mail.
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
        $url = route('portal.solicitacoes.show', $this->viabilityRequestId);

        $assunto = $this->withPlaceholders((string) Settings::get(
            'notificacoes.pendencia.assunto',
            config('sile.notificacoes.pendencia.assunto', "Convite na sua solicitação de viabilidade {$this->protocolNumber}"),
        ));

        $corpo = $this->withPlaceholders((string) Settings::get(
            'notificacoes.pendencia.corpo',
            config('sile.notificacoes.pendencia.corpo', "A análise técnica registrou um convite na sua solicitação {$this->protocolNumber}: {$this->descricao}."),
        ));

        // O corpo parametrizado pode abrir com a saudação; evita duplicar o greeting.
        $corpo = preg_replace('/^Olá!\s*/u', '', $corpo) ?? $corpo;

        return (new MailMessage)
            ->subject($assunto)
            ->greeting('Olá!')
            ->line($corpo)
            ->action('Responder no portal', $url)
            ->line('Acesse o portal SILE, em "Minhas solicitações", para enviar a complementação.')
            ->line('Este é um aviso automático — não responda a esta mensagem.');
    }

    /**
     * Conteúdo do canal in-app (HU-090/096): título, resumo e link do portal.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => CommunicationType::PendenciaAberta->value,
            'title' => CommunicationType::PendenciaAberta->label(),
            'protocol_number' => $this->protocolNumber,
            'summary' => "A análise técnica registrou um convite na solicitação {$this->protocolNumber}: {$this->descricao}",
            'viability_request_id' => $this->viabilityRequestId,
            'url' => route('portal.solicitacoes.show', $this->viabilityRequestId),
        ];
    }

    /**
     * Conteúdo do canal WhatsApp (HU-095): texto curto com link. O destino (to,
     * E.164) é injetado pelo WhatsAppChannel a partir do notifiable.
     */
    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $url = route('portal.solicitacoes.show', $this->viabilityRequestId);

        return new WhatsAppMessage(
            to: '',
            body: "SILE: a análise técnica registrou um convite na sua solicitação {$this->protocolNumber}. Responda no portal: {$url}",
        );
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->emailLogId) {
            EmailLog::find($this->emailLogId)?->markAsFailed($exception->getMessage());
        }
    }

    private function withPlaceholders(string $texto): string
    {
        return strtr($texto, [
            '{protocolo}' => $this->protocolNumber,
            '{pendencia}' => $this->descricao,
        ]);
    }
}
