<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\RelatorioSedeEscritorioVirtualService;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fonte de exportação do relatório sede × abrigados de escritório virtual (Plano
 * R1 — Task 2). Reusa EXATAMENTE o Builder de
 * {@see RelatorioSedeEscritorioVirtualService::builder()} (RN-005 — mesmo recorte
 * da tela, sem filtro reimplementado) e mapeia cada linha por
 * {@see RelatorioSedeEscritorioVirtualService::linha()} (a mesma projeção da
 * consulta). Reconstrutível só a partir do bag (INVARIANTE do {@see ReportSource}):
 * o construtor injeta apenas o serviço (container) e o recorte (`sede`/`inscricao`)
 * viaja no bag serializável — portanto NÃO é SyncOnly.
 *
 * `personalData=true`: expõe a razão social das empresas envolvidas (LGPD).
 */
final class RelatorioSedeReportSource implements ReportSource
{
    public function __construct(private readonly RelatorioSedeEscritorioVirtualService $service) {}

    public function definition(ReportFilters $filtros): ReportDefinition
    {
        // Só o recorte que o serviço conhece (whitelisting do source — RN-005/RN-007).
        $recorte = $filtros->only(['sede', 'inscricao']);

        return new ReportDefinition(
            titulo: 'Relatório sede × abrigados de escritório virtual',
            colunas: [
                ['key' => 'tipo', 'label' => 'Tipo'],
                ['key' => 'tvl', 'label' => 'TVL'],
                ['key' => 'razao_social', 'label' => 'Razão social'],
                ['key' => 'data_emissao', 'label' => 'Data de emissão'],
                ['key' => 'inscricao', 'label' => 'Inscrição imobiliária'],
                ['key' => 'protocolo', 'label' => 'Protocolo'],
            ],
            builder: fn (): Builder => $this->service->builder($recorte),
            mapRow: fn (ViabilityRequest $r): array => array_values($this->service->linha($r)),
            filtrosAplicados: $filtros->aplicados(),
            logName: 'relatorios',
            event: 'exporta-relatorio-sede-ev',
            personalData: true,
            arquivoBase: 'relatorio-sede-escritorio-virtual',
        );
    }
}
