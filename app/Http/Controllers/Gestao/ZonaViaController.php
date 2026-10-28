<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\AlterarZonaViaRequest;
use App\Models\ViabilityRequest;
use App\Services\Analise\AlterarZonaViaService;
use Illuminate\Http\JsonResponse;

/**
 * Edição manual da zona e da via na ficha de análise (permissão editar-zona-via).
 */
class ZonaViaController extends Controller
{
    public function __construct(private AlterarZonaViaService $service) {}

    public function alterar(AlterarZonaViaRequest $request, ViabilityRequest $viabilityRequest): JsonResponse
    {
        $this->service->alterar(
            $viabilityRequest,
            $request->validated('zona'),
            $request->validated('via'),
            $request->user(),
        );

        return response()->json(['status' => 'Zona e via alteradas. A ficha foi recalculada.']);
    }
}
