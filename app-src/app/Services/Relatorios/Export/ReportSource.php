<?php

namespace App\Services\Relatorios\Export;

use App\Services\Relatorios\ReportFilters;

/**
 * Fonte de um relatório (HU-131): constrói a {@see ReportDefinition} a partir de
 * um {@see ReportFilters}. Qualquer tela ganha export CSV/XLSX/PDF do conjunto
 * filtrado apenas adicionando um ReportSource + branch `?formato=` no controller,
 * sem reimplementar a exportação (RN-009).
 *
 * INVARIANTE (RN-005 no assíncrono): um ReportSource deve ser reconstrutível SÓ a
 * partir de um ReportFilters — todo o estado de filtro viaja no BAG serializável.
 * O GerarExportacaoJob reconstrói a definition via
 * `app($sourceClass)->definition(ReportFilters::fromArray($bag))`; as Closures
 * builder/mapRow NUNCA são serializadas (só `sourceClass` + `bag` trafegam).
 *
 * Sources que carregam ESTADO no construtor (ex.: produtividade nominal/escopo —
 * 15-06), não reconstrutíveis só pelo bag, devem implementar o marcador
 * {@see SyncOnlyReportSource} — o {@see ReportExporter} força o caminho síncrono
 * para elas, jamais despachando o Job (que perderia o estado).
 */
interface ReportSource
{
    public function definition(ReportFilters $filtros): ReportDefinition;
}
