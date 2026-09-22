<?php

namespace App\Services\Comunicacao;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Models\Communication;
use App\Models\User;
use App\Notifications\Contracts\ProcessNotification;
use App\Support\Audit\AuditService;
use App\Support\Settings;

/**
 * Roteador multicanal das comunicações de PROCESSO do EP11 (HU-090/094): o ponto
 * ÚNICO onde os toggles de feature e o mapa de canais (HU-014) viram envio real +
 * ledger honesto, sem dupla verdade.
 *
 * Espelha VerifyEmailQueued::freezeUrlFor (resolver no DISPARO, no contexto certo)
 * e o emailLogId do LogNotificationSent (a linha do ledger viaja congelada com a
 * Notification). Os canais são resolvidos de forma SÍNCRONA aqui; o envio em si é
 * enfileirado pela ShouldQueue de cada Notification, e o RegistrarEnvioComunicacao
 * fecha o ciclo (na_fila → enviado/falhou).
 */
class NotificationDispatcher
{
    /**
     * Canal de negócio (CommunicationChannel) → driver nativo do Laravel lido pelo
     * via() de cada Notification e pelo $event->channel de NotificationSent/Failed.
     *
     * @var array<string, string>
     */
    private const DRIVERS = [
        'email' => 'mail',
        'in_app' => 'database',
        'whatsapp' => 'whatsapp',
    ];

    public function __construct(private AuditService $audit) {}

    public function deliver(User $recipient, ProcessNotification $notification): void
    {
        $type = $notification->communicationType();

        /** @var array<int, CommunicationChannel> $resolved */
        $resolved = [];
        /** @var array<string, int> $communicationIds */
        $communicationIds = [];

        foreach ($this->mappedChannels($type->value) as $channel) {
            if ($this->channelEnabled($channel) && $this->preferredByRecipient($recipient, $channel)) {
                $communication = $this->queue($recipient, $notification, $channel);
                $resolved[] = $channel;
                $communicationIds[self::DRIVERS[$channel->value]] = $communication->id;

                continue;
            }

            // Canal no mapa, mas com toggle off: degradação COMUNICADA — registra a
            // linha desativada e audita (nunca falha silenciosa).
            $this->disable($recipient, $notification, $channel);
        }

        // Congela os canais resolvidos (lidos pelo via() no envio enfileirado) e o
        // mapa {driver→communicationId} (lido pelo RegistrarEnvioComunicacao para
        // mail/database e pelo WhatsAppChannel para whatsapp) — sem lookup heurístico.
        $notification->freezeChannels($resolved);
        $this->freezeCommunicationIds($notification, $communicationIds);

        if ($resolved === []) {
            // Nenhum canal habilitado: nada a enviar, apenas o ledger honesto.
            return;
        }

        $recipient->notify($notification);
    }

    /**
     * Canais (CommunicationChannel) do tipo, conforme notificacoes.mapa_canais
     * (HU-014). Canais desconhecidos no mapa são ignorados defensivamente.
     *
     * @return array<int, CommunicationChannel>
     */
    private function mappedChannels(string $type): array
    {
        $mapa = Settings::get('notificacoes.mapa_canais', config('sile.notificacoes.mapa_canais', []));
        $canais = is_array($mapa) ? ($mapa[$type] ?? []) : [];

        return array_values(array_filter(array_map(
            static fn (string $value): ?CommunicationChannel => CommunicationChannel::tryFrom($value),
            is_array($canais) ? $canais : [],
        )));
    }

    private function channelEnabled(CommunicationChannel $channel): bool
    {
        // features.notificacao_email / _in_app / _whatsapp (HU-014). Settings::enabled
        // já cai no fallback de config/sile.php sem banco migrado.
        return Settings::enabled("notificacao_{$channel->value}");
    }

    /**
     * Gancho de preferência de canal por usuário (CONTEXT da Fase 11): hoje no-op —
     * o default é por toggle + mapa. A preferência por usuário entra numa fase
     * futura SEM tocar o roteamento (basta este método passar a consultá-la).
     */
    private function preferredByRecipient(User $recipient, CommunicationChannel $channel): bool
    {
        return true;
    }

    private function queue(User $recipient, ProcessNotification $notification, CommunicationChannel $channel): Communication
    {
        return Communication::create([
            'viability_request_id' => $notification->viabilityRequestId(),
            'recipient_user_id' => $recipient->id,
            'channel' => $channel,
            'type' => $notification->communicationType(),
            'status' => CommunicationStatus::NaFila,
            'title' => $notification->communicationType()->label(),
            'queued_at' => now(),
        ]);
    }

    private function disable(User $recipient, ProcessNotification $notification, CommunicationChannel $channel): void
    {
        $type = $notification->communicationType();

        $communication = Communication::create([
            'viability_request_id' => $notification->viabilityRequestId(),
            'recipient_user_id' => $recipient->id,
            'channel' => $channel,
            'type' => $type,
            'status' => CommunicationStatus::Desativado,
            'title' => $type->label(),
        ]);

        $this->audit->log(
            'notificacoes',
            $type->value,
            "Canal {$channel->label()} desativado por parâmetro (features.notificacao_{$channel->value}) para a comunicação {$type->label()}",
            properties: [
                'viability_request_id' => $notification->viabilityRequestId(),
                'channel' => $channel->value,
                'type' => $type->value,
                'communication_id' => $communication->id,
            ],
            subject: $communication,
            result: 'desativado',
        );
    }

    /**
     * Congela o mapa {driver→communicationId} na Notification, espelhando o
     * emailLogId único do VerifyEmailQueued. As Notifications de processo (11-05/06)
     * declaram `public array $communicationIds = [];` (como declaram emailLogId).
     *
     * @param  array<string, int>  $communicationIds
     */
    private function freezeCommunicationIds(ProcessNotification $notification, array $communicationIds): void
    {
        $notification->communicationIds = $communicationIds;
    }
}
