<?php

namespace App\Services\Relatorios\Export;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Driver PDF do contrato de exportação (HU-131) — dompdf sobre o Blade genérico
 * `relatorios.relatorio`, espelhando o TvlPdfService. O documento traz o rodapé
 * "Total de registros: N" + data/hora + filtros aplicados (CA-07/RN-010). O PDF
 * fica abaixo do limiar assíncrono, então carrega todas as linhas com ->get()
 * (precisa do total). No síncrono streama ao navegador; no assíncrono grava o
 * output() num caminho local do Job.
 */
class PdfExporter implements ReportFormatExporter
{
    public function stream(ReportDefinition $definition): Response
    {
        return $this->render($definition)->stream($definition->fileName('pdf'));
    }

    public function write(ReportDefinition $definition, string $absolutePath): void
    {
        file_put_contents($absolutePath, $this->render($definition)->output());
    }

    private function render(ReportDefinition $definition): DomPdf
    {
        return Pdf::loadView('relatorios.relatorio', $this->dados($definition))
            ->setPaper(
                (string) config('sile.relatorios.export.pdf.paper', 'a4'),
                (string) config('sile.relatorios.export.pdf.orientation', 'portrait'),
            );
    }

    /**
     * Dados do Blade do relatório. Público para servir à verificação do conteúdo
     * (render do Blade nos testes) sem depender da descompressão do PDF — mesmo
     * contrato do PDF renderizado.
     *
     * @return array<string, mixed>
     */
    public function dados(ReportDefinition $definition): array
    {
        $linhas = $definition->builder()->get()
            ->map(fn ($model): array => $definition->mapRow($model))
            ->all();

        return [
            'titulo' => $definition->titulo,
            'colunas' => $definition->columnLabels(),
            'linhas' => $linhas,
            'total' => count($linhas),
            'geradoEm' => now(),
            'filtros' => $definition->filtrosAplicados,
        ];
    }
}
