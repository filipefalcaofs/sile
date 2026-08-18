<?php

namespace Tests\Feature\Relatorios\Stubs;

use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Source de teste reconstrutível SÓ pelo bag (invariante do contrato — RN-005):
 * filtra ViabilityRequest por bairro lido do ReportFilters. O Job reconstrói via
 * `app(self::class)->definition(ReportFilters::fromArray($bag))`, provando que o
 * conjunto filtrado é idêntico no síncrono E no assíncrono.
 */
class ProcessoBairroSource implements ReportSource
{
    public function definition(ReportFilters $filtros): ReportDefinition
    {
        $bairro = $filtros->bairro();

        return new ReportDefinition(
            titulo: 'Processos por bairro',
            colunas: [['key' => 'bairro', 'label' => 'Bairro']],
            builder: fn (): Builder => ViabilityRequest::query()
                ->when($bairro, fn (Builder $q, string $b): Builder => $q->where('address_neighborhood', $b))
                ->orderBy('id'),
            mapRow: fn (ViabilityRequest $r): array => [$r->address_neighborhood],
            filtrosAplicados: $filtros->aplicados(),
            logName: 'relatorios',
            event: 'exporta-processos',
            arquivoBase: 'processos',
        );
    }
}
