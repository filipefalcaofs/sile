<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\SimulacaoSolicitacaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Simulação pré-protocolo da viabilidade (HU-141): executa os motores reais da
 * Fase 7 com os dados já informados (imóvel/área/atividades), por CNAE,
 * propagando o veredito do motor LOUOS. O resultado é ORIENTATIVO — não muda o
 * status nem bloqueia o protocolo (RN-002, direito de petição). Só o dono em
 * rascunho aciona (ViabilityRequestPolicy::update, CA-04). O toggle
 * features.simulacao_solicitacao degrada de forma comunicada quando off (RN-005).
 */
class SolicitacaoSimulacaoController extends Controller
{
    public function __construct(private SimulacaoSolicitacaoService $simulacao) {}

    public function store(ViabilityRequest $solicitacao): RedirectResponse
    {
        Gate::authorize('update', $solicitacao);

        $resultado = $this->simulacao->simulate($solicitacao);

        return back()
            ->with('status', 'Simulação de viabilidade executada. O resultado é orientativo e não impede o protocolo.')
            ->with('simulacao', $resultado);
    }
}
