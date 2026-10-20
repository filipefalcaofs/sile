<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Models\ViabilityRequest;
use App\Services\Analise\ProcessoQueryService;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\SaturacaoService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Exportação do observatório de saturação: as solicitações deferidas que
 * formam os pares bairro × CNAE do recorte (RN-005).
 */
final class SaturacaoReportSource extends SolicitacoesReportSource
{
    public function __construct(ProcessoQueryService $processos, private readonly SaturacaoService $saturacao)
    {
        parent::__construct($processos);
    }

    protected function populacao(ReportFilters $filtros): Builder
    {
        return $this->saturacao->solicitacoes($filtros)
            ->with(ProcessoQueryService::RELACOES_LISTAGEM)
            ->orderByDesc('viability_requests.protocoled_at')
            ->orderByDesc('viability_requests.id');
    }

    protected function bairro(ViabilityRequest $solicitacao): ?string
    {
        return $solicitacao->bairro_oficial ?? $solicitacao->address_neighborhood;
    }
}
