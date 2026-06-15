<?php

namespace App\Services\Relatorios\Export;

use App\Jobs\GerarExportacaoJob;
use App\Models\User;
use App\Services\Relatorios\ReportFilters;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Orquestrador do contrato único de exportação (HU-131 — coração da fase). Recebe
 * um {@see ReportSource} + {@see ReportFilters} + formato e decide o caminho:
 *
 * - Abaixo do limiar (relatorios.export.assincrono_limiar_linhas): exporta
 *   SÍNCRONO, devolvendo o streaming do driver (CA-06 — a tela responde na hora).
 * - Acima do limiar: despacha o {@see GerarExportacaoJob} carregando o BAG
 *   COMPLETO (`$filtros->toArray()`), que reconstrói EXATAMENTE o mesmo conjunto
 *   filtrado no worker (RN-005 no assíncrono) e responde 202 (a tela não trava).
 *
 * GUARDA SyncOnly: sources que implementam {@see SyncOnlyReportSource} carregam
 * estado de construtor (não reconstrutível só pelo bag) e são SEMPRE síncronas —
 * nunca vão ao Job, que perderia o estado.
 *
 * Toda exportação é AUDITADA (RN-008) antes de ramificar, com tela/filtros/
 * formato/volume e a marca de dado pessoal da definition (LGPD).
 */
class ReportExporter
{
    /**
     * Formatos com driver disponível HOJE. XLSX (openspout) entra em 15-08; até
     * lá é honestamente recusado, mesmo constando do catálogo (anti-fachada).
     *
     * @var list<string>
     */
    private const DRIVERS_DISPONIVEIS = ['csv', 'pdf'];

    public function __construct(private readonly AuditService $audit) {}

    public function export(ReportSource $source, ReportFilters $filtros, string $formato, ?User $user = null): Response
    {
        $this->validarFormato($formato);

        $definition = $source->definition($filtros);
        $total = $definition->builder()->count();

        $this->audit->log(
            logName: $definition->logName,
            event: $definition->event.'-'.$formato,
            description: "Exportação {$formato} de \"{$definition->titulo}\"",
            properties: [
                'tela' => $definition->titulo,
                'filtros' => $filtros->aplicados(),
                'formato' => $formato,
                'volume' => $total,
            ],
            personalData: $definition->personalData,
        );

        $forceSync = $source instanceof SyncOnlyReportSource;
        $limiar = (int) Settings::get(
            'relatorios.export.assincrono_limiar_linhas',
            config('sile.relatorios.export.assincrono_limiar_linhas', 5000),
        );

        if ($forceSync || $total <= $limiar) {
            return $this->driver($formato)->stream($definition);
        }

        // O bag completo garante que o Job reconstrói o MESMO conjunto filtrado
        // (RN-005 no assíncrono). As Closures builder/mapRow nunca são serializadas.
        GerarExportacaoJob::dispatch($source::class, $filtros->toArray(), $formato, $user?->id);

        return response()->noContent(202);
    }

    private function validarFormato(string $formato): void
    {
        $habilitados = (array) Settings::get(
            'relatorios.export.formatos_habilitados',
            config('sile.relatorios.export.formatos_habilitados', self::DRIVERS_DISPONIVEIS),
        );

        $disponiveis = array_values(array_intersect($habilitados, self::DRIVERS_DISPONIVEIS));

        if (! in_array($formato, $disponiveis, true)) {
            throw new InvalidArgumentException("Formato de exportação não disponível: {$formato}.");
        }
    }

    private function driver(string $formato): ReportFormatExporter
    {
        return match ($formato) {
            'csv' => app(CsvExporter::class),
            'pdf' => app(PdfExporter::class),
            default => throw new InvalidArgumentException("Formato de exportação não suportado: {$formato}."),
        };
    }
}
