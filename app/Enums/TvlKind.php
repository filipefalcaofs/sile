<?php

namespace App\Enums;

/**
 * Tipo de validade do TVL na decisão. Provisória e pré-operacional têm
 * validade e podem ser renovadas; definitiva encerra o ciclo de prorrogação.
 */
enum TvlKind: string
{
    case Provisoria = 'provisoria';
    case Definitiva = 'definitiva';
    case PreOperacional = 'pre_operacional';

    public function label(): string
    {
        return match ($this) {
            self::Provisoria => 'Provisória',
            self::Definitiva => 'Definitiva',
            self::PreOperacional => 'Pré-operacional',
        };
    }

    public function renovavel(): bool
    {
        return $this !== self::Definitiva;
    }
}
