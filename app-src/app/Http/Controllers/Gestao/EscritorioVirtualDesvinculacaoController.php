<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\DesvincularInscricaoRequest;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use App\Services\EscritorioVirtual\DesvincularInscricaoService;
use Illuminate\Http\RedirectResponse;

/**
 * Gatilho MANUAL de desvinculação da inscrição da sede de escritório virtual
 * (RN-EV-06). Enquanto a mudança de endereço automática (revisão/REDESIM) e os
 * desfechos de cassação (spec-2) não existem, o gestor pode desvincular a
 * inscrição de uma sede pela retaguarda. Delega ao serviço COMPARTILHADO
 * (DesvincularInscricaoService). Gated por emitir-tvl (autoridade do produto).
 */
class EscritorioVirtualDesvinculacaoController extends Controller
{
    public function __construct(private DesvincularInscricaoService $desvincular) {}

    public function __invoke(
        DesvincularInscricaoRequest $request,
        ViabilityRequest $viabilityRequest,
    ): RedirectResponse {
        $lock = VirtualOfficeInscriptionLock::query()
            ->where('sede_viability_request_id', $viabilityRequest->id)
            ->where('active', true)
            ->latest('id')
            ->first();

        if ($lock === null) {
            return back()->with('error', 'Não há inscrição de sede ativa para desvincular neste processo.');
        }

        $resultado = $this->desvincular->desvincular(
            $lock,
            $request->string('motivo')->toString(),
            $request->user(),
        );

        return back()->with(
            'status',
            "Inscrição desvinculada da sede. {$resultado['abrigados_notificados']} abrigado(s) notificado(s).",
        );
    }
}
