<?php

namespace App\Services\Expresso;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityDecision;

/**
 * Retorno imutável do motor de decisão expressa (FluxoExpressoService::decide,
 * 09-05): o estado final da solicitação, a decisão criada (quando defere/
 * indefere) e o motivo (quando encaminha à análise). `emitted` indica se o
 * ResultadoEmitido foi disparado nesta chamada — false nos caminhos sem
 * emissão (análise) e na corrida idempotente que reaproveita a decisão já
 * gravada (não redispara o evento).
 *
 * É o insumo do job/comando (09-06) e da UI de retaguarda (09-11): nunca um
 * resultado simulado — só reflete o que o motor efetivamente decidiu.
 */
final readonly class DecisionResult
{
    public function __construct(
        public ViabilityRequestStatus $status,
        public ?ViabilityDecision $decision,
        public ?string $reason,
        public bool $emitted,
    ) {}

    /**
     * Encaminhamento à análise técnica (sem decisão vinculante): toggle off,
     * semi-expresso (CNAE fora do expresso) ou veredito pendente (sem zona). NÃO
     * cria ViabilityDecision e NÃO dispara ResultadoEmitido.
     */
    public static function paraAnalise(string $reason): self
    {
        return new self(ViabilityRequestStatus::EmAnalise, null, $reason, emitted: false);
    }

    /**
     * Decisão vinculante efetivada (deferida/indeferida). `emitted` é false
     * quando a decisão já existia (corrida idempotente) e o evento não deve ser
     * redisparado.
     */
    public static function decidida(ViabilityDecision $decision, bool $emitted = true): self
    {
        $status = $decision->isDeferida()
            ? ViabilityRequestStatus::Deferida
            : ViabilityRequestStatus::Indeferida;

        return new self($status, $decision, null, $emitted);
    }
}
