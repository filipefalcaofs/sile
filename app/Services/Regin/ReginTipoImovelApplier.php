<?php

namespace App\Services\Regin;

use App\Models\ViabilityRequest;
use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;

/**
 * Grava o tipo de imóvel enviado pelo REGIN na solicitação. Não decide
 * encaminhamento — valor desconhecido fica cru, sem código normalizado.
 */
class ReginTipoImovelApplier
{
    public function apply(ViabilityRequest $request, ?string $rawDoRegin): TipoImovel
    {
        $tipo = TipoImovel::fromRegin($rawDoRegin, TipoImovelCatalog::vigente());

        $cru = $rawDoRegin === null || trim($rawDoRegin) === '' ? null : $rawDoRegin;

        $request->tipo_imovel = $cru;
        $request->tipo_imovel_normalized = $tipo->normalized;
        $request->save();

        return $tipo;
    }
}
