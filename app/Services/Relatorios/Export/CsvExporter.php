<?php

namespace App\Services\Relatorios\Export;

use Symfony\Component\HttpFoundation\Response;

/**
 * Driver CSV do contrato de exportação (HU-131) — consolida o padrão de streaming
 * já provado no AuditoriaController::export (fputcsv + chunk). Sem linha de total
 * no corpo (RN-010 — CSV preserva a integridade tabular). No síncrono streama por
 * php://output; no assíncrono grava num file handle do caminho local do Job.
 */
class CsvExporter implements ReportFormatExporter
{
    public function stream(ReportDefinition $definition): Response
    {
        return response()->streamDownload(function () use ($definition): void {
            $saida = fopen('php://output', 'w');
            $this->escrever($definition, $saida);
            fclose($saida);
        }, $definition->fileName('csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function write(ReportDefinition $definition, string $absolutePath): void
    {
        $saida = fopen($absolutePath, 'w');
        $this->escrever($definition, $saida);
        fclose($saida);
    }

    /**
     * Cabeçalho (rótulos das colunas) + linhas do conjunto filtrado em chunks
     * (RN-005, baixa memória). O mesmo loop serve ao síncrono e ao assíncrono.
     *
     * @param  resource  $saida
     */
    private function escrever(ReportDefinition $definition, $saida): void
    {
        $chunk = (int) config('sile.relatorios.export.chunk', 200);

        fputcsv($saida, $definition->columnLabels());

        $definition->builder()->chunk($chunk, function ($linhas) use ($saida, $definition): void {
            foreach ($linhas as $model) {
                fputcsv($saida, $definition->mapRow($model));
            }
        });
    }
}
