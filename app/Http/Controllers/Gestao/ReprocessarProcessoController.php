<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\ViabilityRequest;
use App\Services\Motor\ReprocessarEtapasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReprocessarProcessoController extends Controller
{
    public function store(Request $request, ViabilityRequest $viabilityRequest, ReprocessarEtapasService $servico): RedirectResponse
    {
        $resultado = $servico->reprocessar($viabilityRequest, $request->user());

        return back()->with('status', $resultado->mensagem);
    }
}
