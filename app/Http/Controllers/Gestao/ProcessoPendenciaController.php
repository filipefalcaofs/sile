<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\AbrirPendenciaRequest;
use App\Models\ViabilityRequest;
use App\Services\Analise\PendenciaInvalidaException;
use App\Services\Analise\PendenciaService;
use Illuminate\Http\RedirectResponse;

/**
 * Abertura de pendência pelo analista (HU-083). Controller FINO: valida a
 * descrição (AbrirPendenciaRequest) e delega ao PendenciaService::abrir (10-11),
 * que transiciona em_analise→em_pendencia, audita (RN-002) e notifica o
 * requerente. Gated por analisar-processos (403 auditado — CA-04); abrir fora de
 * em_analise lança PendenciaInvalidaException, traduzida em 422 (aviso
 * controlado, nunca falha silenciosa).
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

        return back()->with('status', 'Pendência aberta. O processo aguarda a resposta do requerente.');
    }
}
