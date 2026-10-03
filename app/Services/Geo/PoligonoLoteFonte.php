<?php

namespace App\Services\Geo;

use App\Enums\LotePoligonoFonte;
use App\Models\ViabilityRequest;

/**
 * Uma origem de geometria do lote. Implementações não decidem precedência:
 * quem ordena é o {@see PoligonoLoteResolver}.
 */
interface PoligonoLoteFonte
{
    public function fonte(): LotePoligonoFonte;

    public function buscar(ViabilityRequest $request): PoligonoLoteHit;
}
