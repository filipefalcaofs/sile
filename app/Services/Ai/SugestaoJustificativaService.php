<?php

namespace App\Services\Ai;

use App\Jobs\Ai\SugestaoJustificativaJob;
use App\Models\AnalysisRecord;
use App\Models\ViabilityRequest;

/**
 * Camada de serviço da sugestão de justificativa por IA (regra SEDUR
 * 22/09/2026, item 6). Degradação honesta tripla — NÃO despacha, NÃO chama o
 * provedor e NÃO simula quando: (1) o toggle features.ia_justificativa está
 * desligado ou não há provedor de TEXTO ativo; (2) a ficha não tem a
 * pré-análise do motor (engine_snapshot) — sem enquadramento real a citar;
 * (3) o analista AINDA NÃO decidiu o enquadramento da atividade
 * (status_escolhido deferida/indeferida) — a IA redige a justificativa DA
 * decisão do analista, nunca antes dela. A minuta resultante é SEMPRE
 * sugestão revisável: o job só cria a AiSuggestion e preenche o campo vazio;
 * nunca grava decisão.
 */
class SugestaoJustificativaService
{
    /** Decisões do analista que habilitam a sugestão (a regra do botão). */
    private const DECISOES = ['deferida', 'indeferida'];

    public function __construct(private readonly AiFeatureGate $gate) {}

    /**
     * Despacha a sugestão de justificativa do CNAE como SUGESTÃO revisável.
     *
     * @return bool true se a função estava disponível, havia motor E o analista
     *              já decidiu a atividade, com o job despachado; false caso
     *              contrário — o analista redige manualmente.
     */
    public function processar(ViabilityRequest $processo, string $cnae, ?int $userId = null): bool
    {
        if (! $this->gate->available('justificativa', 'text')) {
            return false;
        }

        $ficha = $processo->currentAnalysisRecord;

        if (! $this->temFundamentacaoDoMotor($ficha)) {
            return false;
        }

        $decisao = $this->decisaoDoCnae($ficha, $cnae);

        if ($decisao === null) {
            return false;
        }

        SugestaoJustificativaJob::dispatch($processo->id, $cnae, $decisao, $userId);

        return true;
    }

    /**
     * Decisão do analista para o CNAE na revisão vigente (deferida/indeferida).
     * Null quando o CNAE não está na ficha ou ainda não foi decidido — caso em
     * que a sugestão fica bloqueada (a regra do botão da ficha).
     */
    public function decisaoDoCnae(?AnalysisRecord $ficha, string $cnae): ?string
    {
        foreach ($ficha?->per_cnae ?? [] as $item) {
            if (! is_array($item) || (string) ($item['cnae'] ?? '') !== $cnae) {
                continue;
            }

            $decisao = (string) ($item['status_escolhido'] ?? '');

            return in_array($decisao, self::DECISOES, true) ? $decisao : null;
        }

        return null;
    }

    /**
     * Há enquadramento REAL do motor quando a revisão vigente foi pré-analisada
     * (engine_available) e tem um engine_snapshot não vazio.
     */
    private function temFundamentacaoDoMotor(?AnalysisRecord $ficha): bool
    {
        return $ficha !== null
            && $ficha->engine_available === true
            && is_array($ficha->engine_snapshot)
            && $ficha->engine_snapshot !== [];
    }
}
