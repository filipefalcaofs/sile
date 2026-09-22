<?php

namespace App\Notifications;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationType;
use App\Enums\DecisionOutcome;
use App\Notifications\Contracts\ProcessNotification;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de ciência do resultado do fluxo expresso (HU-077). A partir do EP11
 * (11-06) é uma ProcessNotification multicanal: percorre o NotificationDispatcher
 * (HU-090/094), que resolve os canais (notificacoes.mapa_canais ∩ toggles), cria
 * o ledger `communications` (in-app + histórico HU-096) e CONGELA os canais aqui
 * via {@see freezeChannels()}. No envio enfileirado o via() LÊ {@see channels()}
 * (sem reresolver toggles) e traduz cada CommunicationChannel para o driver
 * nativo; o RegistrarEnvioComunicacao (11-04) fecha o ledger (na_fila →
 * enviado/falhou) pelo mapa {driver→communicationId} congelado em
 * {@see $communicationIds} — substitui o antigo par EmailLog + failed() (a fonte
 * de verdade das comunicações de PROCESSO agora é o ledger, sem dupla verdade).
 *
 * NÃO anexa o TVL/PDF (RN-004): o canal oficial de entrega do documento de
 * viabilidade ao cidadão é o Regin/SEFAZ (decisão SEDUR). O e-mail é apenas o
 * aviso do desfecho, com assunto parametrizável pela HU-014
 * (expresso.notificacao.assunto_deferida / _indeferida) — por isso o toMail
 * NÃO anexa qualquer documento (nenhuma chamada de anexação).
 */
class ResultadoExpressoNotification extends Notification implements ProcessNotification, ShouldQueue
{
    use Queueable;

    /**
     * Canais congelados no disparo pelo dispatcher; lidos pelo via() no envio.
     *
     * @var array<int, CommunicationChannel>
     */
    public array $frozenChannels = [];

    /**
     * Mapa {driver nativo → communications.id} congelado pelo dispatcher; lido
     * pelo RegistrarEnvioComunicacao para fechar o ledger (espelha o emailLogId).
     *
     * @var array<string, int>
     */
    public array $communicationIds = [];

    public function __construct(
        public string $protocolNumber,
        public DecisionOutcome $outcome,
        public ?int $viabilityRequestId = null,
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
     * Dinâmico: lê os canais congelados pelo dispatcher e os traduz para os
     * drivers nativos do Laravel. Fora do dispatcher (sem canais congelados),
     * cai no e-mail — o canal histórico do aviso de resultado.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = $this->channels();

        if ($channels === []) {
            return ['mail'];
        }

        return array_map(static fn (CommunicationChannel $channel): string => match ($channel) {
            CommunicationChannel::Email => 'mail',
            CommunicationChannel::InApp => 'database',
            CommunicationChannel::Whatsapp => 'whatsapp',
        }, $channels);
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

    /**
     * Conteúdo da notificação in-app (canal database): o desfecho e o link de
     * acompanhamento no portal — base da central de notificações (HU-096).
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $deferida = $this->outcome === DecisionOutcome::Deferida;

        return [
            'type' => CommunicationType::Resultado->value,
            'protocol_number' => $this->protocolNumber,
            'outcome' => $this->outcome->value,
            'title' => "Resultado da viabilidade {$this->protocolNumber}: {$this->outcome->value}",
            'message' => $deferida
                ? "A sua solicitação de viabilidade de protocolo {$this->protocolNumber} foi deferida."
                : "A sua solicitação de viabilidade de protocolo {$this->protocolNumber} foi indeferida.",
            'url' => $this->acompanhamentoUrl(),
        ];
    }

    private function acompanhamentoUrl(): ?string
    {
        if ($this->viabilityRequestId === null) {
            return null;
        }

        return route('portal.solicitacoes.show', $this->viabilityRequestId);
    }
}
