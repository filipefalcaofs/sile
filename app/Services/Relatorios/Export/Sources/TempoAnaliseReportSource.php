<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\TempoAnaliseService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fonte de exportação do DETALHAMENTO de tempo por processo (HU-129/HU-131): cada
 * linha é um processo protocolado no período com os minutos ÚTEIS de cada etapa
 * (preenchimento/espera/análise/pendência) + total, na MESMA semântica do
 * {@see TempoAnaliseService} (desconto de fim de semana/feriado via
 * businessDurationBetween). O conjunto é EXATAMENTE o filtrado (RN-005).
 *
 * `personalData=false`: as colunas trazem só o número do processo e tempos — sem
 * PII do requerente. Reconstrutível só a partir do bag (INVARIANTE do
 * {@see ReportSource}): nenhum estado de construtor, segue o caminho síncrono OU
 * assíncrono sem perder filtro. O builder eager-carrega `transitions` para o
 * mapRow pareá-las por processo (o desconto de tempo útil é PHP — não há SQL
 * portável; ver TempoAnaliseService).
 */
final class TempoAnaliseReportSource implements ReportSource
{
    public function __construct(private readonly TempoAnaliseService $tempos) {}

    public function definition(ReportFilters $filtros): ReportDefinition
    {
        return new ReportDefinition(
            titulo: 'Tempo por etapa (detalhamento por processo)',
            colunas: [
                ['key' => 'protocolo', 'label' => 'Processo'],
                ['key' => 'preenchimento_min', 'label' => 'Preenchimento (min úteis)'],
                ['key' => 'espera_min', 'label' => 'Espera (min úteis)'],
                ['key' => 'analise_min', 'label' => 'Análise (min úteis)'],
                ['key' => 'pendencia_min', 'label' => 'Pendência (min úteis)'],
                ['key' => 'total_min', 'label' => 'Total (min úteis)'],
            ],
            builder: fn (): Builder => $this->builder($filtros),
            mapRow: fn (ViabilityRequest $processo): array => $this->linha($processo),
            filtrosAplicados: $filtros->aplicados(),
            logName: 'relatorios',
            event: 'exporta-tempo-analise',
            personalData: false,
            arquivoBase: 'tempo-por-etapa',
        );
    }

    /**
     * Processos protocolados no período (mesmo recorte da agregação), com as
     * transições carregadas para o cálculo por etapa.
     *
     * @return Builder<ViabilityRequest>
     */
    private function builder(ReportFilters $filtros): Builder
    {
        return ViabilityRequest::query()
            ->with('transitions')
            ->whereNotNull('viability_requests.protocoled_at')
            ->when($filtros->from(), fn (Builder $q, $from): Builder => $q->where('viability_requests.protocoled_at', '>=', $from))
            ->when($filtros->to(), fn (Builder $q, $to): Builder => $q->where('viability_requests.protocoled_at', '<=', $to))
            ->when($filtros->setorId(), fn (Builder $q, int $id): Builder => $q->where('viability_requests.sector_id', $id))
            ->when($filtros->analistaId(), fn (Builder $q, int $id): Builder => $q->where('viability_requests.assigned_user_id', $id))
            ->orderByDesc('viability_requests.id');
    }

    /**
     * Linha do detalhamento (ordem alinhada às colunas): minutos úteis por etapa
     * do processo + total. Etapa ausente vira 0 (não ocorreu).
     *
     * @return array<int, scalar|null>
     */
    private function linha(ViabilityRequest $processo): array
    {
        $minutos = $this->tempos->minutosPorEtapaDoProcesso($processo);

        return [
            $processo->protocol_number,
            $minutos['preenchimento'] ?? 0,
            $minutos['espera'] ?? 0,
            $minutos['analise'] ?? 0,
            $minutos['pendencia'] ?? 0,
            array_sum($minutos),
        ];
    }
}
