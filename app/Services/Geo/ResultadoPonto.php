<?php

namespace App\Services\Geo;

use App\Enums\ConfirmacaoPonto;
use App\Enums\PropertyLocationSource;

/**
 * Ponto final do imóvel depois da conferência: o do Cadastro Imobiliário
 * (confirmado, não confirmado ou não verificado) ou o de outra fonte
 * (substituído). As distâncias são até o eixo da rua da inscrição.
 */
final readonly class ResultadoPonto
{
    public function __construct(
        public ConfirmacaoPonto $confirmacao,
        public float $lat,
        public float $lng,
        public PropertyLocationSource $origem,
        public ?float $distanciaOriginalM = null,
        public ?float $distanciaFinalM = null,
    ) {}
}
