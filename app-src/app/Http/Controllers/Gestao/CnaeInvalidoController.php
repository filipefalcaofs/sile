<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\CorrigirCnaeInvalidoRequest;
use App\Models\ViabilityRequest;
use App\Services\Analise\CorrigirCnaeInvalidoService;
use Illuminate\Http\JsonResponse;

/**
 * Correção, na ficha de análise, do código CNAE que a comparação com a viabilidade
 * da SEFAZ marcou como inválido.
 */
class CnaeInvalidoController extends Controller
{
    public function __construct(private CorrigirCnaeInvalidoService $service) {}

    public function corrigir(CorrigirCnaeInvalidoRequest $request, ViabilityRequest $viabilityRequest): JsonResponse
    {
        $this->service->corrigir(
            $viabilityRequest,
            (string) $request->validated('codigo_atual'),
            (string) $request->validated('codigo_novo'),
            $request->user(),
        );

        return response()->json(['status' => 'Código corrigido. A ficha foi recalculada.']);
    }
}
