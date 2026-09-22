<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ProtocolarSolicitacaoRequest;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\DocumentacaoIncompletaException;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use App\Services\Solicitacao\SolicitacaoIncompletaException;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Protocolar a solicitação (HU-068): só o dono em rascunho (ViabilityRequestPolicy
 * ::protocol, CA-04). Delega ao ProtocolarSolicitacaoService (número único +
 * transição auditada + evento de domínio após o commit) e traduz os bloqueios de
 * domínio (documento obrigatório faltante, dados mínimos ausentes) em flash.error
 * — aviso comunicado, nunca silencioso (HU-067). O toggle
 * features.solicitacao_viabilidade degrada de forma comunicada quando off.
 */
class ProtocoloController extends Controller
{
    public function __construct(private ProtocolarSolicitacaoService $protocolar) {}

    public function store(ProtocolarSolicitacaoRequest $request, ViabilityRequest $solicitacao): RedirectResponse
    {
        Gate::authorize('protocol', $solicitacao);

        if (! Settings::enabled('solicitacao_viabilidade')) {
            return back()->with('status', 'O protocolo de solicitações de viabilidade está temporariamente desativado pelo administrador.');
        }

        try {
            $this->protocolar->protocol(
                $solicitacao,
                $request->user(),
                $request->boolean('proceed_despite'),
            );
        } catch (DocumentacaoIncompletaException|SolicitacaoIncompletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('portal.solicitacoes.index')
            ->with('status', "Solicitação protocolada com sucesso sob o número {$solicitacao->protocol_number}.");
    }
}
