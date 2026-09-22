<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Models\AnalysisPendency;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\PendenciasRelatorioService;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Fonte de exportação do relatório de pendências/exigências: reusa EXATAMENTE
 * o builder/linha do {@see PendenciasRelatorioService} (RN-005 — mesmo recorte
 * da tela). Reconstrutível só a partir do bag (INVARIANTE do {@see ReportSource}).
 *
 * `personalData=true`: a coluna Analista expõe nome de servidor (dado pessoal
 * de trabalho) — relevante para o painel LGPD.
 */
final class PendenciasReportSource implements ReportSource
{
    public function __construct(private readonly PendenciasRelatorioService $service) {}

    public function definition(ReportFilters $filtros): ReportDefinition
    {
        return new ReportDefinition(
            titulo: 'Pendências e exigências',
            colunas: [
                ['key' => 'processo', 'label' => 'Processo'],
                ['key' => 'descricao', 'label' => 'Exigência'],
                ['key' => 'status_label', 'label' => 'Situação'],
                ['key' => 'analista', 'label' => 'Analista'],
                ['key' => 'aberta_em', 'label' => 'Aberta em'],
                ['key' => 'limite_em', 'label' => 'Prazo-limite'],
                ['key' => 'respondida_em', 'label' => 'Respondida em'],
                ['key' => 'tempo_resposta_minutos', 'label' => 'Tempo de resposta (min)'],
            ],
            builder: fn (): Builder => $this->service->builder($filtros),
            mapRow: fn (AnalysisPendency $p): array => $this->linha($p),
            filtrosAplicados: $filtros->aplicados(),
            logName: 'relatorios',
            event: 'exporta-pendencias',
            personalData: true,
            arquivoBase: 'pendencias-exigencias',
        );
    }

    /**
     * Linha exportada (ordem alinhada às colunas): reusa a projeção da tela e
     * formata as datas para pt-BR.
     *
     * @return array<int, scalar|null>
     */
    private function linha(AnalysisPendency $p): array
    {
        $l = $this->service->linha($p);

        return [
            $l['processo'],
            $l['descricao'],
            $l['status_label'],
            $l['analista'],
            $l['aberta_em'] !== null ? Carbon::parse($l['aberta_em'])->format('d/m/Y H:i') : null,
            $l['limite_em'] !== null ? Carbon::parse($l['limite_em'])->format('d/m/Y H:i') : null,
            $l['respondida_em'] !== null ? Carbon::parse($l['respondida_em'])->format('d/m/Y H:i') : null,
            $l['tempo_resposta_minutos'],
        ];
    }
}
