<?php

namespace App\Services\Relatorios;

use App\Enums\TipoGatilho;
use App\Enums\ViabilityRequestStatus;
use App\Models\ExpressoQueda;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestTransition;
use App\Services\Analise\ProcessoQueryService;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de quedas do fluxo expresso (HU-145), fechando o ciclo de melhoria
 * contínua do expresso (medir → parametrizar HU-143 → medir) sobre DADO REAL: a
 * taxa de resposta expressa, sua série temporal, o ranking de motivos da queda
 * (sobre a captura do 15-07) e o drill-down até os processos que caíram.
 *
 * Espelha o {@see IndicadoresViabilidadeService}: route-free, sem estado,
 * agregação em SQL (`groupBy`/`selectRaw`/`date()` portável SQLite/PostgreSQL),
 * NUNCA loop PHP sobre coleção carregada. Toda degradação é HONESTA (anti-
 * fachada, CA-03): taxa null sem base no período, meta null quando não definida
 * (RN-004 — nunca inventada), motivo null rotulado 'não classificado'.
 *
 * Definições (RN-002):
 *  - RESPONDIDAS pelo expresso = decisões `flow='expresso'` com
 *    `decided_by_user_id IS NULL` (auto-decisão sem analista) no período.
 *  - ELEGÍVEIS (entraram no expresso) = transições `protocolada→{deferida|
 *    indeferida|em_analise}` no período (toda protocolada avaliada gera uma).
 */
class ExpressoQuedaService
{
    /** Fluxo da auto-decisão expressa (≠ analise_tecnica humana). */
    private const FLOW_EXPRESSO = 'expresso';

    /** Transições que marcam a ENTRADA no fluxo expresso (denominador elegível). */
    private const TO_STATUS_ELEGIVEL = [
        ViabilityRequestStatus::Deferida->value,
        ViabilityRequestStatus::Indeferida->value,
        ViabilityRequestStatus::EmAnalise->value,
    ];

    /**
     * Taxa de resposta expressa no período (HU-145 RN-002): respondidas pelo
     * expresso ÷ elegíveis. `taxa` é null sem base no período (honesto, jamais
     * 0% fabricado); `meta` vem de `relatorios.expresso.meta_taxa` (RN-004) e é
     * null quando não definida — NUNCA inventada.
     *
     * @return array{respondidas: int, elegiveis: int, taxa: float|null, meta: float|null}
     */
    public function taxaRespostaExpressa(ReportFilters $f): array
    {
        $respondidas = $this->respondidasBase($f)->count();
        $elegiveis = $this->elegiveisBase($f)->count();

        return [
            'respondidas' => $respondidas,
            'elegiveis' => $elegiveis,
            'taxa' => $elegiveis > 0 ? round($respondidas / $elegiveis * 100, 1) : null,
            'meta' => $this->metaTaxa(),
        ];
    }

    /**
     * Série temporal da taxa por dia dentro da janela (HU-145): para cada dia
     * com entrada no expresso, respondidas ÷ elegíveis do dia. A janela é o
     * período do filtro ou, na ausência, os últimos `relatorios.expresso.janela_dias`
     * (parametrizável). Lista ordenada por dia; sem base no período → vazia.
     *
     * @return list<array{dia: string, respondidas: int, elegiveis: int, taxa: float|null}>
     */
    public function serieTemporal(ReportFilters $f): array
    {
        $dias = (int) Settings::get('relatorios.expresso.janela_dias', 30);
        $inicio = $f->from() ?? Carbon::now()->subDays($dias)->startOfDay();
        $fim = $f->to() ?? Carbon::now()->endOfDay();

        $respondidas = ViabilityDecision::query()
            ->where('flow', self::FLOW_EXPRESSO)
            ->whereNull('decided_by_user_id')
            ->whereBetween('decided_at', [$inicio, $fim])
            ->groupBy('dia')
            ->get([
                DB::raw('date(decided_at) as dia'),
                DB::raw('count(*) as total'),
            ])
            ->mapWithKeys(fn ($linha): array => [(string) $linha->dia => (int) $linha->total])
            ->all();

        $elegiveisPorDia = ViabilityRequestTransition::query()
            ->where('from_status', ViabilityRequestStatus::Protocolada->value)
            ->whereIn('to_status', self::TO_STATUS_ELEGIVEL)
            ->whereBetween('created_at', [$inicio, $fim])
            ->groupBy('dia')
            ->get([
                DB::raw('date(created_at) as dia'),
                DB::raw('count(*) as total'),
            ])
            ->mapWithKeys(fn ($linha): array => [(string) $linha->dia => (int) $linha->total])
            ->all();

        ksort($elegiveisPorDia);

        return array_values(array_map(function (int $elegiveis, string $dia) use ($respondidas): array {
            $resp = $respondidas[$dia] ?? 0;

            return [
                'dia' => $dia,
                'respondidas' => $resp,
                'elegiveis' => $elegiveis,
                'taxa' => $elegiveis > 0 ? round($resp / $elegiveis * 100, 1) : null,
            ];
        }, $elegiveisPorDia, array_keys($elegiveisPorDia)));
    }

    /**
     * Ranking dos motivos da queda por gatilho (HU-145): {@see ExpressoQueda}
     * agrupada por `tipo_gatilho` com `count(*)` desc. O gatilho null (motor
     * degradado ou queda sem gatilho de contexto) é rotulado 'não classificado'
     * — honesto, jamais somado a um gatilho real (RN-001).
     *
     * @return list<array{tipo_gatilho: string|null, rotulo: string, total: int}>
     */
    public function rankingMotivos(ReportFilters $f): array
    {
        return ExpressoQueda::query()
            ->when($f->from(), fn (Builder $q, Carbon $from): Builder => $q->where('created_at', '>=', $from))
            ->when($f->to(), fn (Builder $q, Carbon $to): Builder => $q->where('created_at', '<=', $to))
            ->groupBy('tipo_gatilho')
            ->orderByDesc('total')
            ->orderBy('tipo_gatilho')
            ->get([
                'tipo_gatilho',
                DB::raw('count(*) as total'),
            ])
            ->map(fn ($linha): array => [
                'tipo_gatilho' => $linha->tipo_gatilho,
                'rotulo' => $this->rotuloGatilho($linha->tipo_gatilho),
                'total' => (int) $linha->total,
            ])
            ->all();
    }

    /**
     * Drill-down dos processos que CAÍRAM ao analista (HU-145): reusa o filtro
     * completo do SAPS ({@see ProcessoQueryService::filtered}, RN-005) recortado
     * aos processos com queda registrada — o caminho até as divergências
     * analista×motor ({@see App\Models\AnalysisDivergence}) na ficha de cada um.
     *
     * @return Builder<ViabilityRequest>
     */
    public function drillDown(ReportFilters $f): Builder
    {
        return app(ProcessoQueryService::class)
            ->filtered($f->toProcessoFiltros())
            ->whereIn(
                'viability_requests.id',
                ExpressoQueda::query()->select('viability_request_id'),
            );
    }

    /**
     * Builder das RESPONDIDAS pelo expresso no período (auto-decisão sem analista).
     *
     * @return Builder<ViabilityDecision>
     */
    private function respondidasBase(ReportFilters $f): Builder
    {
        return ViabilityDecision::query()
            ->where('flow', self::FLOW_EXPRESSO)
            ->whereNull('decided_by_user_id')
            ->when($f->from(), fn (Builder $q, Carbon $from): Builder => $q->where('decided_at', '>=', $from))
            ->when($f->to(), fn (Builder $q, Carbon $to): Builder => $q->where('decided_at', '<=', $to));
    }

    /**
     * Builder dos ELEGÍVEIS (entraram no expresso) no período: transições
     * protocolada→{deferida|indeferida|em_analise}.
     *
     * @return Builder<ViabilityRequestTransition>
     */
    private function elegiveisBase(ReportFilters $f): Builder
    {
        return ViabilityRequestTransition::query()
            ->where('from_status', ViabilityRequestStatus::Protocolada->value)
            ->whereIn('to_status', self::TO_STATUS_ELEGIVEL)
            ->when($f->from(), fn (Builder $q, Carbon $from): Builder => $q->where('created_at', '>=', $from))
            ->when($f->to(), fn (Builder $q, Carbon $to): Builder => $q->where('created_at', '<=', $to));
    }

    /**
     * Meta (%) da taxa expressa do parâmetro `relatorios.expresso.meta_taxa`
     * (HU-014/RN-004): null quando não definida — NUNCA inventada.
     */
    private function metaTaxa(): ?float
    {
        $meta = Settings::get('relatorios.expresso.meta_taxa');

        return $meta !== null && $meta !== '' ? (float) $meta : null;
    }

    /**
     * Rótulo legível do gatilho (TipoGatilho) — null vira 'não classificado'
     * (motor degradado / queda sem gatilho), código desconhecido fica como veio.
     */
    private function rotuloGatilho(?string $codigo): string
    {
        if ($codigo === null) {
            return 'não classificado';
        }

        return TipoGatilho::tryFrom($codigo)?->label() ?? $codigo;
    }
}
