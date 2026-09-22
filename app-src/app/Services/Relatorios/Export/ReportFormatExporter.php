<?php

namespace App\Services\Relatorios\Export;

use Symfony\Component\HttpFoundation\Response;

/**
 * Driver de UM formato de exportação (HU-131 RN-009). CSV e PDF nascem em 15-02;
 * XLSX (openspout) entra em 15-08. Cada driver sabe streamar ao navegador
 * (síncrono, abaixo do limiar) e gravar num caminho de filesystem (assíncrono,
 * pelo GerarExportacaoJob para download assinado posterior).
 */
interface ReportFormatExporter
{
    /**
     * Caminho SÍNCRONO: streama o conteúdo do conjunto filtrado direto ao cliente.
     */
    public function stream(ReportDefinition $definition): Response;

    /**
     * Caminho ASSÍNCRONO: grava o conteúdo num caminho de filesystem real (o
     * arquivo só é registrado/baixável após a escrita bem-sucedida — anti-fachada).
     */
    public function write(ReportDefinition $definition, string $absolutePath): void;
}
