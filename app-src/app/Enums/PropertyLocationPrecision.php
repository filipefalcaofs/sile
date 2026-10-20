<?php

namespace App\Enums;

/**
 * Precisão do ponto geocodificado: o Nominatim casou o número do imóvel ou
 * achou só a via.
 */
enum PropertyLocationPrecision: string
{
    case Numero = 'numero';
    case Via = 'via';
}
