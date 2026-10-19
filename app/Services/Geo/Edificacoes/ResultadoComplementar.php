<?php

namespace App\Services\Geo\Edificacoes;

use App\Enums\PoligonoFonte;
use App\Enums\StatusResolucaoPoligono;

/**
 * Resultado da busca do polígono nas edificações complementares.
 */
final readonly class ResultadoComplementar
{
    public function __construct(
        public StatusResolucaoPoligono $status,
        public ?PoligonoFonte $fonte = null,
        public bool $precisaIa = false,
    ) {}
}
