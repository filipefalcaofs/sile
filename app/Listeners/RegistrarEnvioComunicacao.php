<?php

namespace App\Listeners;

use App\Models\Communication;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Fecha o ciclo do ledger communications (HU-096): marca a linha enviado
 * (NotificationSent) ou falhou (NotificationFailed) resolvendo-a pelo MAPA
 * {driver→communicationId} que o NotificationDispatcher (11-04) congela na
 * Notification — espelha o emailLogId único do LogNotificationSent + gate por
 * channel.
 *
 * AUTO-DESCOBERTO por type-hint do evento em cada handle* (NUNCA Event::listen —
 * lição Fases 8/9: o registro manual duplicaria a marcação; a contagem trava).
 *
 * Trata SOMENTE os canais mail e database. whatsapp é DELIBERADAMENTE excluído:
 * o WhatsAppChannel (11-03) é o dono ÚNICO da linha whatsapp — só ele distingue
 * enviado de bloqueado. Como esse canal NÃO relança a exceção no caminho
 * degradado, o Laravel dispara NotificationSent('whatsapp') mesmo bloqueado;
 * tratar whatsapp aqui sobrescreveria bloqueado→enviado (um "enviado" fictício,
 * proibido pela entrega-funcional).
 *
 * Notificações de CONTA (verificação de e-mail/senha) NÃO têm o mapa congelado:
 * o lookup devolve null e o LogNotificationSent segue cuidando do EmailLog — sem
 * duplicar nem conflitar.
 */
class RegistrarEnvioComunicacao
{
    /**
     * Canais nativos cujo resultado de entrega o ledger acompanha por evento de
     * notificação. whatsapp fica de fora de propósito (ver docblock da classe).
     *
     * @var list<string>
     */
    private const TRACKED_CHANNELS = ['mail', 'database'];

    public function handleNotificationSent(NotificationSent $event): void
    {
        $this->resolveLedgerLine($event->notification, $event->channel)?->markAsSent();
    }

    public function handleNotificationFailed(NotificationFailed $event): void
    {
        $this->resolveLedgerLine($event->notification, $event->channel)?->markAsFailed($this->motivo($event));
    }

    /**
     * Resolve a linha communications pelo mapa congelado na Notification, gated
     * por canal (mail/database). Notificações sem o mapa (conta) → null.
     */
    private function resolveLedgerLine(object $notification, string $channel): ?Communication
    {
        if (! in_array($channel, self::TRACKED_CHANNELS, true)) {
            return null;
        }

        /** @var array<string, int> $map */
        $map = $notification->communicationIds ?? [];

        if (! is_array($map) || ! isset($map[$channel])) {
            return null;
        }

        return Communication::find($map[$channel]);
    }

    private function motivo(NotificationFailed $event): string
    {
        $exception = $event->data['exception'] ?? null;

        if ($exception instanceof \Throwable && $exception->getMessage() !== '') {
            return $exception->getMessage();
        }

        return 'Falha no envio da notificação.';
    }
}
