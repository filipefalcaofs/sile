<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Models\ViabilityRequest;
use App\Services\Analise\ProcessoQueryService;
use App\Services\Relatorios\GeoBairroIndicadorService;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Exportação do painel por bairro: as solicitações que o resumo conta, com o
 * bairro oficial e o digitado de reserva, como na tela (RN-005).
 */
final class GeoBairroReportSource extends SolicitacoesReportSource
{
    public function __construct(ProcessoQueryService $processos, private readonly GeoBairroIndicadorService $geo)
    {
        parent::__construct($processos);
    }

    protected function populacao(ReportFilters $filtros): Builder
    {
        return $this->geo->solicitacoes($filtros)
            ->with(ProcessoQueryService::RELACOES_LISTAGEM)
            ->orderByDesc('viability_requests.protocoled_at')
            ->orderByDesc('viability_requests.id');
    }

    protected function bairro(ViabilityRequest $solicitacao): ?string
    {
        return $solicitacao->bairro_oficial ?? $solicitacao->address_neighborhood;
    }
}
