<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\DecisionOutcome;
use App\Http\Controllers\Controller;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnaliseTecnicaDecisionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        if ($record === null) {
            throw ValidationException::withMessages([
                'decisao' => 'A ficha de análise ainda não foi criada para este processo.',
            ]);
        }

        try {
            $result = $this->decision->decide($record, $request->user());
        } catch (DomainException $e) {
            // Erro acionável na própria tela (relatório SEDUR 21/09, item 07) —
            // nunca a página genérica de 422.
            throw ValidationException::withMessages(['decisao' => $e->getMessage()]);
        }

        $mensagem = $result->outcome === DecisionOutcome::Deferida
            ? 'Processo deferido e concluído.'
            : 'Processo indeferido e concluído.';

        return back()->with('status', $mensagem);
    }
}
