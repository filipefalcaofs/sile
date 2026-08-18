<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\AnalysisPendencyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\AbrirPendenciaRequest;
use App\Http\Requests\Gestao\CancelarConviteRequest;
use App\Models\AnalysisPendency;
use App\Models\ViabilityRequest;
use App\Services\Analise\PendenciaInvalidaException;
use App\Services\Analise\PendenciaService;
use Illuminate\Http\RedirectResponse;

/**
 * Abertura e cancelamento de convite (ex-pendência) pelo analista (HU-083 +
 * relatório SEDUR 2026-07-09). Controller FINO: valida e delega ao
 * PendenciaService, que transiciona o estado, audita (RN-002) e notifica.
 * Gated por analisar-processos (403 auditado — CA-04); operação inválida no
 * estado atual lança PendenciaInvalidaException, traduzida em aviso controlado.
 */
class ProcessoPendenciaController extends Controller
{
    public function __construct(private PendenciaService $pendencias) {}

    public function store(AbrirPendenciaRequest $request, ViabilityRequest $viabilityRequest): RedirectResponse
    {
        try {
            $this->pendencias->abrir($viabilityRequest, $request->user(), $request->validated('descricao'));
        } catch (PendenciaInvalidaException $e) {
            abort(422, $e->getMessage());
        }

        return back()->with('status', 'Convite aberto. O processo aguarda a resposta do requerente.');
    }

    /**
     * Cancela o convite aberto do processo, exigindo um parecer com o motivo
     * (relatório SEDUR 2026-07-09). O serviço reabre a análise (em_pendencia→
     * em_analise). Sem convite aberto ou estado inválido → aviso controlado.
     */
    public function cancelar(CancelarConviteRequest $request, ViabilityRequest $viabilityRequest): RedirectResponse
    {
        $convite = AnalysisPendency::query()
            ->where('viability_request_id', $viabilityRequest->id)
            ->where('status', AnalysisPendencyStatus::Aberta)
            ->latest('id')
            ->first();

        if ($convite === null) {
            return back()->with('error', 'Não há convite aberto para cancelar neste processo.');
        }

        try {
            $this->pendencias->cancelar($convite, $request->user(), $request->validated('parecer'));
        } catch (PendenciaInvalidaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Convite cancelado. A análise foi reaberta.');
    }
}
