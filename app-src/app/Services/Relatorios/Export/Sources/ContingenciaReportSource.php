<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Models\ViabilityRequest;
use App\Services\Relatorios\ContingenciaRelatorioService;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Fonte de exportação do relatório de atendimento em contingência: reusa
 * EXATAMENTE o builder/linha do {@see ContingenciaRelatorioService} (RN-005 —
 * mesmo recorte da tela). Reconstrutível só a partir do bag (INVARIANTE do
 * {@see ReportSource}).
 *
 * `personalData=true`: a coluna Operador expõe nome de servidor (dado pessoal
 * de trabalho) — relevante para o painel LGPD.
 */
final class ContingenciaReportSource implements ReportSource
{
    public function __construct(private readonly ContingenciaRelatorioService $service) {}

    public function definition(ReportFilters $filtros): ReportDefinition
    {
        return new ReportDefinition(
            titulo: 'Atendimento em contingência',
            colunas: [
                ['key' => 'processo', 'label' => 'Processo'],
                ['key' => 'motivo', 'label' => 'Motivo'],
                ['key' => 'operador', 'label' => 'Operador'],
                ['key' => 'protocolado_em', 'label' => 'Protocolado em'],
                ['key' => 'status_label', 'label' => 'Situação'],
            ],
            builder: fn (): Builder => $this->service->builder($filtros),
            mapRow: fn (ViabilityRequest $r): array => $this->linha($r),
            filtrosAplicados: $filtros->aplicados(),
            logName: 'relatorios',
            event: 'exporta-contingencia',
            personalData: true,
            arquivoBase: 'atendimento-contingencia',
        );
    }

    /**
     * Linha exportada (ordem alinhada às colunas): reusa a projeção da tela e
     * formata a data para pt-BR.
     *
     * @return array<int, scalar|null>
     */
    private function linha(ViabilityRequest $r): array
    {
        $l = $this->service->linha($r);

        return [
            $l['processo'],
            $l['motivo'],
            $l['operador'],
            $l['protocolado_em'] !== null ? Carbon::parse($l['protocolado_em'])->format('d/m/Y H:i') : null,
            $l['status_label'],
        ];
    }
}
