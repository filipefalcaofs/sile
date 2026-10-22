<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\ViabilityRequest;
use App\Services\Geo\MapaDecisaoService;
use Illuminate\Http\JsonResponse;

/**
 * Camadas do mapa que justificam a zona e a via do processo (lote, zonas,
 * linhas de centralidade e vias). Separado do detalhe porque consulta o
 * GeoServer da SEDUR; o detalhe do processo abre sem esperar por ele.
 */
class MapaDecisaoController extends Controller
{
    public function __invoke(ViabilityRequest $viabilityRequest, MapaDecisaoService $mapa): JsonResponse
    {
        return response()->json($mapa->montar($viabilityRequest));
    }
}
