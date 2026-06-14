<?php

namespace App\Enums;

/**
 * Etapa atual do SLA da análise (HU-144). Distribuicao = aguardando o analista
 * assumir na caixa do setor; Analise = em análise efetiva. Extensível — alinha
 * com os parâmetros analise.sla.<etapa>_dias (cada etapa tem seu prazo).
 */
enum AnalysisStage: string
{
    case Distribuicao = 'distribuicao';
    case Analise = 'analise';

    public function label(): string
    {
        return match ($this) {
            self::Distribuicao => 'Distribuição',
            self::Analise => 'Análise',
        };
    }
}
