<?php

namespace App\Enums;

/**
 * Tipo de validade do TVL na decisão. Provisória pode ser renovada;
 * definitiva encerra o ciclo de prorrogação.
 */
enum TvlKind: string
{
    case Provisoria = 'provisoria';
    case Definitiva = 'definitiva';

    public function label(): string
    {
        return match ($this) {
            self::Provisoria => 'Provisória',
            self::Definitiva => 'Definitiva',
        };
    }
}
