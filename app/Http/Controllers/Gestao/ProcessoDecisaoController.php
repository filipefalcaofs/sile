<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\DecisionOutcome;
use App\Http\Controllers\Controller;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnaliseTecnicaDecisionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Decisão técnica do analista (HU-086/087/088/089) — deferir/indeferir/encerrar.
 * Controller FINO: resolve a ficha vigente (currentAnalysisRecord) e delega ao
 * AnaliseTecnicaDecisionService::decide (10-10), onde vivem a regra RN-004, a
 * transição (encerramento), a auditoria síncrona e o disparo de ResultadoEmitido.
 * Gated por analisar-processos (403 auditado no ponto único — CA-04). Ficha em
 * rascunho / processo fora de em_analise → DomainException do serviço, traduzida
 * em 422 (não decide). Nada de regra é reimplementado aqui.
 */
class ProcessoDecisaoController extends Controller
{
    public function __construct(private AnaliseTecnicaDecisionService $decision) {}

    public function __invoke(Request $request, ViabilityRequest $viabilityRequest): RedirectResponse
    {
        $record = $viabilityRequest->currentAnalysisRecord;

        abort_if($record === null, 422, 'A ficha de análise ainda não foi criada para este processo.');

        try {
            $result = $this->decision->decide($record, $request->user());
        } catch (DomainException $e) {
            abort(422, $e->getMessage());
        }

        $mensagem = $result->outcome === DecisionOutcome::Deferida
            ? 'Processo deferido e concluído.'
            : 'Processo indeferido e concluído.';

        return back()->with('status', $mensagem);
    }
}
