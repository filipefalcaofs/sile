<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Support\Vocabulario;
use Illuminate\Http\JsonResponse;

class MetadataController extends Controller
{
    /**
     * Fonte única dos rótulos de enum de negócio (Fase 6.1). O mesmo
     * catálogo vai na prop Inertia `vocabulario`.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(Vocabulario::catalog());
    }
}
