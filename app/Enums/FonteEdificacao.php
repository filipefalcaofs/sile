<?php

namespace App\Enums;

/**
 * Origem de uma edificação da base local building_footprints.
 */
enum FonteEdificacao: string
{
    case Overture = 'overture';
    case Microsoft = 'microsoft';
    case Ia = 'ia';

    public function paraPoligonoFonte(): PoligonoFonte
    {
        return match ($this) {
            self::Overture => PoligonoFonte::Overture,
            self::Microsoft => PoligonoFonte::Microsoft,
            self::Ia => PoligonoFonte::Ia,
        };
    }
}
