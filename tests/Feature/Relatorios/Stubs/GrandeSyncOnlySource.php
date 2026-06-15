<?php

namespace Tests\Feature\Relatorios\Stubs;

use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\SyncOnlyReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Source de teste marcado como SyncOnly (carrega estado de construtor, não
 * reconstrutível só pelo bag): mesmo com volume ACIMA do limiar assíncrono, o
 * ReportExporter força o caminho síncrono — nunca despacha o Job, que perderia o
 * estado.
 */
class GrandeSyncOnlySource implements SyncOnlyReportSource
{
    public function definition(ReportFilters $filtros): ReportDefinition
    {
        return new ReportDefinition(
            titulo: 'Relatório com estado de construtor',
            colunas: [['key' => 'bairro', 'label' => 'Bairro']],
            builder: fn (): Builder => ViabilityRequest::query()->orderBy('id'),
            mapRow: fn (ViabilityRequest $r): array => [$r->address_neighborhood],
            filtrosAplicados: $filtros->aplicados(),
            arquivoBase: 'relatorio-estado',
        );
    }
}
