<?php

namespace App\Enums;

/**
 * Tipo da vistoria (identificação da ficha). Definido na abertura da ficha —
 * não é campo digitado pelo vistoriador.
 */
enum InspectionType: string
{
    case Localizacao = 'localizacao';
    case Funcionamento = 'funcionamento';
    case Publicidade = 'publicidade';

    public function label(): string
    {
        return match ($this) {
            self::Localizacao => 'Vistoria de localização',
            self::Funcionamento => 'Vistoria de funcionamento',
            self::Publicidade => 'Vistoria de publicidade',
        };
    }
}
