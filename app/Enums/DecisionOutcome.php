<?php

namespace App\Enums;

/**
 * Desfecho da decisão do fluxo expresso (HU-076). Só existe quando há decisão
 * vinculante: em_analise/pendente NÃO criam ViabilityDecision. O mapeamento
 * a partir do ResultadoViabilidade consolidado (permitido→deferida,
 * nao_permitido→indeferida, pendente→sem decisão) é responsabilidade do
 * FluxoExpressoService (09-05), onde mora a consolidação RN-009.
 */
enum DecisionOutcome: string
{
    case Deferida = 'deferida';
    case Indeferida = 'indeferida';

    public function label(): string
    {
        return match ($this) {
            self::Deferida => 'Deferida',
            self::Indeferida => 'Indeferida',
        };
    }
}
