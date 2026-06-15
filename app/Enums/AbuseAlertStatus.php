<?php

namespace App\Enums;

/**
 * Estado de um alerta de abuso/fraude (HU-149). Aberto = recém-detectado,
 * aguardando triagem humana; Confirmado/Descartado = baixa dada por um gestor
 * com justificativa. Só o estado Aberto participa do índice único parcial que
 * garante a idempotência da detecção (1 alerta aberto por regra/fingerprint).
 */
enum AbuseAlertStatus: string
{
    case Aberto = 'aberto';
    case Confirmado = 'confirmado';
    case Descartado = 'descartado';

    public function label(): string
    {
        return match ($this) {
            self::Aberto => 'Aberto',
            self::Confirmado => 'Confirmado',
            self::Descartado => 'Descartado',
        };
    }

    /**
     * O alerta já foi triado (baixa dada)? Confirmado e descartado liberam um
     * novo alerta aberto futuro com o mesmo fingerprint.
     */
    public function isResolved(): bool
    {
        return $this !== self::Aberto;
    }
}
