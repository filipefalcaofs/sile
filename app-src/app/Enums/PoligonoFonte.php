<?php

namespace App\Enums;

/**
 * Fonte que forneceu o polígono do lote gravado em address_polygon_geojson.
 */
enum PoligonoFonte: string
{
    case Chave = 'chave';
    case Semap = 'semap';
    case Ponto = 'ponto';

    public function label(): string
    {
        return match ($this) {
            self::Chave => 'Endereçamento por CODLOG e número',
            self::Semap => 'Cadastro municipal (SEMAP)',
            self::Ponto => 'Endereçamento pelo ponto do imóvel',
        };
    }
}
