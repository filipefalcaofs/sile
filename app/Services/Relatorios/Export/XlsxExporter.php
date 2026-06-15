<?php

namespace App\Services\Relatorios\Export;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Driver XLSX do contrato de exportação (HU-131) — openspout v4 por STREAMING de
 * baixa memória: itera o Builder filtrado da tela com ->cursor() (RN-005, nunca
 * all() em memória) e escreve linha a linha.
 *
 * Pitfall 4: o XLSX é um ZIP e exige um arquivo de filesystem gravável (não um
 * php://output puro). Por isso o assíncrono grava direto no caminho real do Job
 * (openToFile) e o síncrono gera o XLSX num arquivo temporário e o streama ao
 * cliente com fpassthru — evitando o openToBrowser do openspout, que chama
 * header()/ob_end_clean() por fora e conflita com o ciclo da StreamedResponse do
 * Symfony (e impossibilitaria asserir o Content-Type na resposta).
 */
final class XlsxExporter implements ReportFormatExporter
{
    private const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function stream(ReportDefinition $definition): Response
    {
        $temporario = tempnam(sys_get_temp_dir(), 'xlsx');
        $this->escrever($definition, $temporario);

        return response()->streamDownload(function () use ($temporario): void {
            $handle = fopen($temporario, 'rb');
            fpassthru($handle);
            fclose($handle);
            @unlink($temporario);
        }, $definition->fileName('xlsx'), ['Content-Type' => self::CONTENT_TYPE]);
    }

    public function write(ReportDefinition $definition, string $absolutePath): void
    {
        $this->escrever($definition, $absolutePath);
    }

    /**
     * Cabeçalho (rótulos em negrito) + linhas do conjunto filtrado por streaming
     * (RN-005, baixa memória via cursor()). O mesmo loop serve ao síncrono e ao
     * assíncrono.
     */
    private function escrever(ReportDefinition $definition, string $absolutePath): void
    {
        $options = new Options;
        $options->SHOULD_USE_INLINE_STRINGS = true;

        $writer = new Writer($options);
        $writer->openToFile($absolutePath);

        $writer->addRow(Row::fromValues($definition->columnLabels(), (new Style)->setFontBold()));

        foreach ($definition->builder()->cursor() as $model) {
            $writer->addRow(Row::fromValues(array_values($definition->mapRow($model))));
        }

        $writer->close();
    }
}
