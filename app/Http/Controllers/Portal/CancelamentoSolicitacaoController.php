<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\CancelarSolicitacaoRequest;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\CancelamentoNaoPermitidoException;
use App\Services\Solicitacao\CancelarSolicitacaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Cancelar a solicitação (HU-070): só o dono enquanto não decidido
 * (ViabilityRequestPolicy::cancel, CA-04). Delega ao CancelarSolicitacaoService
 * (transição auditada + timeline, com estados canceláveis parametrizáveis) e
 * traduz o bloqueio de estado em flash.error — aviso comunicado, nunca
 * silencioso (CA-03).
 */
class CancelamentoSolicitacaoController extends Controller
{
    public function __construct(private CancelarSolicitacaoService $cancelar) {}

    public function destroy(CancelarSolicitacaoRequest $request, ViabilityRequest $solicitacao): RedirectResponse
    {
        Gate::authorize('cancel', $solicitacao);

        try {
            $this->cancelar->cancel($solicitacao, $request->user(), $request->validated('reason'));
        } catch (CancelamentoNaoPermitidoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('portal.solicitacoes.index')
            ->with('status', 'Solicitação cancelada com sucesso.');
    }
}
