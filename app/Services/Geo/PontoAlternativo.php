<?php

namespace App\Services\Geo;

use App\Enums\PropertyLocationSource;

/**
 * Ponto do imóvel obtido por outra fonte que não o Cadastro Imobiliário.
 */
final readonly class PontoAlternativo
{
    public function __construct(
        public float $lat,
        public float $lng,
        public PropertyLocationSource $fonte,
    ) {}
}
