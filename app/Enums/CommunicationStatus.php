<?php

namespace App\Enums;

/**
 * Status HONESTO de uma comunicação no ledger communications. Nunca registra
 * "enviado" fictício:
 * - NaFila: criada e enfileirada, aguardando o envio do canal.
 * - Enviado: o canal confirmou a entrega (NotificationSent).
 * - Falhou: o canal tentou e falhou (NotificationFailed) — com error_message.
 * - Bloqueado: canal habilitado, mas indisponível no disparo (ex.: gateway de
 *   WhatsApp fora do ar) — não houve entrega, e isso fica auditado.
 * - Desativado: canal desligado por toggle (HU-014) — degradação controlada,
 *   sem fingir envio.
 */
enum CommunicationStatus: string
{
    case NaFila = 'na_fila';
    case Enviado = 'enviado';
    case Falhou = 'falhou';
    case Bloqueado = 'bloqueado';
    case Desativado = 'desativado';

    public function label(): string
    {
        return match ($this) {
            self::NaFila => 'Na fila',
            self::Enviado => 'Enviado',
            self::Falhou => 'Falhou',
            self::Bloqueado => 'Bloqueado',
            self::Desativado => 'Desativado',
        };
    }
}
