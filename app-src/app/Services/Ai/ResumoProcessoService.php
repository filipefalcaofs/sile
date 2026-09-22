<?php

namespace App\Services\Ai;

use App\Enums\AiSuggestionType;
use App\Jobs\Ai\ResumoProcessoJob;
use App\Models\AiSuggestion;
use App\Models\ViabilityRequest;
use Throwable;

/**
 * Camada de serviço do resumo do processo por IA (HU-117). Degradação honesta:
 * sem o toggle features.ia_resumo ligado E um provedor de TEXTO ativo, NÃO
 * chama o provedor e NÃO simula (anti-fachada). A ficha precisa do resultado
 * no mesmo request (prop deferida + botão "Gerar resumo"): dispatch assíncrono
 * deixava o card em polling até estourar "ainda não chegou da fila", e cada
 * reload reenfileirava outro job. Por isso o job roda em dispatchSync; o
 * RunAiAgentJob ainda re-checa o portão e a idempotência por input_hash.
 */
class ResumoProcessoService
{
    public function __construct(private readonly AiFeatureGate $gate) {}

    /**
     * Gera o resumo do processo como SUGESTÃO revisável no mesmo request.
     *
     * @return bool true se a função estava disponível e a síntese ficou pronta
     *              (já existia ou acabou de ser gerada); false quando
     *              indisponível (toggle/provedor) ou quando o provedor falhou.
     */
    public function processar(ViabilityRequest $processo, ?int $userId = null): bool
    {
        if (! $this->gate->available('resumo', 'text')) {
            return false;
        }

        if ($this->resumoExistente($processo) !== null) {
            return true;
        }

        try {
            ResumoProcessoJob::dispatchSync($processo->id, $userId);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        return $this->resumoExistente($processo) !== null;
    }

    private function resumoExistente(ViabilityRequest $processo): ?AiSuggestion
    {
        return AiSuggestion::query()
            ->where('viability_request_id', $processo->id)
            ->where('type', AiSuggestionType::ResumoProcesso)
            ->orderByDesc('id')
            ->first();
    }
}
