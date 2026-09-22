<?php

namespace App\Services\Relatorios\Export;

/**
 * Marcador (interface vazia) para {@see ReportSource}s que carregam ESTADO no
 * construtor e NÃO são reconstrutíveis apenas a partir de um ReportFilters (ex.:
 * produtividade nominal/escopo — 15-06). O {@see ReportExporter} FORÇA o caminho
 * síncrono (streaming) para essas fontes, nunca despachando o GerarExportacaoJob
 * — que reconstruiria o source só pela classe + bag, perdendo o estado.
 */
interface SyncOnlyReportSource extends ReportSource {}
