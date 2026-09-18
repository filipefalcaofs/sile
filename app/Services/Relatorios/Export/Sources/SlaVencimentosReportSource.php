<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\SlaVencimentosService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Fonte de exportação do relatório de SLA e vencimentos da análise: reusa
 * EXATAMENTE o builder/linha do {@see SlaVencimentosService} (RN-005 — mesmo
 * recorte da tela, sem filtro reimplementado). Reconstrutível só a partir do
 * bag (INVARIANTE do {@see ReportSource}): o construtor injeta apenas o
 * serviço (container), sem estado de filtro.
 *
 * `personalData=true`: a coluna Analista expõe nome de servidor (dado pessoal
 * de trabalho) — relevante para o painel LGPD.
 */
final class SlaVencimentosReportSource implements ReportSource
{
    public function __construct(private readonly SlaVencimentosService $service) {}

    public function definition(ReportFilters $filtros): ReportDefinition
    {
        return new ReportDefinition(
            titulo: 'SLA e vencimentos da análise',
            colunas: [
                ['key' => 'processo', 'label' => 'Processo'],
                ['key' => 'etapa', 'label' => 'Etapa'],
                ['key' => 'setor', 'label' => 'Setor'],
                ['key' => 'analista', 'label' => 'Analista'],
                ['key' => 'iniciado_em', 'label' => 'Início da etapa'],
                ['key' => 'limite_em', 'label' => 'Prazo-limite'],
                ['key' => 'situacao_label', 'label' => 'Situação'],
                ['key' => 'restante', 'label' => 'Tempo restante'],
            ],
            builder: fn (): Builder => $this->service->builder($filtros),
            mapRow: fn (ViabilityRequest $r): array => $this->linha($r),
            filtrosAplicados: $filtros->aplicados(),
            logName: 'relatorios',
            event: 'exporta-sla-vencimentos',
            personalData: true,
            arquivoBase: 'sla-vencimentos',
        );
    }

    /**
     * Linha exportada (ordem alinhada às colunas): reusa a projeção da tela e
     * formata as datas para pt-BR.
     *
     * @return array<int, scalar|null>
     */
    private function linha(ViabilityRequest $r): array
    {
        $l = $this->service->linha($r);

        return [
            $l['processo'],
            $l['etapa'],
            $l['setor'],
            $l['analista'],
            $l['iniciado_em'] !== null ? Carbon::parse($l['iniciado_em'])->format('d/m/Y H:i') : null,
            $l['limite_em'] !== null ? Carbon::parse($l['limite_em'])->format('d/m/Y H:i') : null,
            $l['situacao_label'],
            $l['restante'],
        ];
    }
}
