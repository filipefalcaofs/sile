<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\UpdateRiskTriggerRequest;
use App\Models\RiskTrigger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manutenção dos gatilhos semi-expresso (HU-049/HU-051, parametrização
 * HU-014): dado administrável, não registry de código. SEM create/destroy —
 * o `codigo` é enum-bound (TipoGatilho) e cada caso tem comportamento no
 * motor (RiscoClassificationService::applyGatilhos); uma linha criada pela
 * UI sem caso no enum seria fachada. O CRUD gerencia titulo/motivo/ativo dos
 * códigos conhecidos; desligar um gatilho muda o roteamento (ação sensível,
 * auditada via HasAuditoria — RN-002).
 */
class RiskTriggerController extends Controller
{
    /**
     * Lista completa, sem paginação: são os 3 gatilhos conhecidos (novos
     * entram via enum + motor + seed, nunca pela tela).
     */
    public function index(): Response
    {
        $gatilhos = RiskTrigger::query()
            ->orderBy('codigo')
            ->get()
            ->map(fn (RiskTrigger $trigger) => [
                'id' => $trigger->id,
                'codigo' => $trigger->codigo->value,
                'codigo_label' => $trigger->codigo->label(),
                'titulo' => $trigger->titulo,
                'motivo' => $trigger->motivo,
                'ativo' => $trigger->ativo,
            ]);

        return Inertia::render('gestao/gatilhos-risco/index', [
            'gatilhos' => $gatilhos,
        ]);
    }

    /**
     * Atualiza título e motivo. O codigo/categoria são imutáveis (ausentes do
     * UpdateRequest, logo descartados — padrão CPF/CNAE).
     */
    public function update(UpdateRiskTriggerRequest $request, RiskTrigger $riskTrigger): RedirectResponse
    {
        $riskTrigger->update($request->validated());

        return back()->with('status', 'Gatilho de risco atualizado com sucesso.');
    }

    /**
     * Liga/desliga o gatilho. NUNCA exclui o registro: a desativação apenas o
     * retira do escopo ativos() que o motor avalia — processos que caiam
     * nele passam a concluir no fluxo expresso.
     */
    public function toggleActivation(RiskTrigger $riskTrigger): RedirectResponse
    {
        $riskTrigger->update(['ativo' => ! $riskTrigger->ativo]);

        $message = $riskTrigger->ativo
            ? 'Gatilho reativado — processos voltam a ser encaminhados à análise técnica.'
            : 'Gatilho desativado — processos passam a concluir automaticamente no fluxo expresso.';

        return back()->with('status', $message);
    }
}
