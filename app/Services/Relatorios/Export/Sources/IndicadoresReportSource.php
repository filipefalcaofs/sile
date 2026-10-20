<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Services\Analise\ProcessoQueryService;
use App\Services\Relatorios\IndicadoresViabilidadeService;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Exportação da tela de Indicadores: as solicitações protocoladas que os
 * indicadores de volume contam, com os mesmos filtros (RN-005).
 */
final class IndicadoresReportSource extends SolicitacoesReportSource
{
    public function __construct(ProcessoQueryService $processos, private readonly IndicadoresViabilidadeService $indicadores)
    {
        parent::__construct($processos);
    }

    protected function populacao(ReportFilters $filtros): Builder
    {
        return $this->indicadores->solicitacoes($filtros)
            ->with(ProcessoQueryService::RELACOES_LISTAGEM)
            ->orderByDesc('viability_requests.protocoled_at')
            ->orderByDesc('viability_requests.id');
    }
}
