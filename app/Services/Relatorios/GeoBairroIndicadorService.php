<?php

namespace App\Services\Relatorios;

use App\Enums\AnalysisCategory;
use App\Enums\DecisionOutcome;
use App\Models\ViabilityRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Painel geoeconômico por bairro (Geo BI interno — Módulo 1). Consolida, por
 * AGREGAÇÃO SQL real (espelhando {@see IndicadoresViabilidadeService}: Builder +
 * `when()` + `groupBy`/`selectRaw`, nunca loop PHP), a distribuição das
 * solicitações PROTOCOLADAS por bairro, com deferidas/indeferidas e taxa de
 * deferimento — insumo de planejamento da SEDUR (onde a atividade econômica
 * concentra e como decide).
 *
 * Degradação HONESTA (entrega funcional sem fachada): a zona urbanística oficial
 * (Quadro LOUOS / GIS GeoServer SEDUR) está pendente; em vez de inventar uma
 * zona, o recorte é por `address_neighborhood` (dado real já carregado). A
 * fidelidade plena por zona/polígono entra quando a base oficial for liberada
 * (PostGIS) — muda a carga, não a lógica.
 *
 * Route-free e sem estado: recebe um {@see ReportFilters} e devolve arrays
 * prontos para a UI. Período sem dados → lista vazia; bairro sem decisão → taxa
 * null (jamais 0% fabricado). População = solicitações com `protocoled_at` (CA-03).
 */
class GeoBairroIndicadorService
{
    /**
     * Resumo do recorte: total protocolado, bairros distintos e quantos sem
     * bairro informado (transparência da degradação — não viram bucket fantasma).
     *
     * @return array{total: int, bairros_distintos: int, sem_bairro: int}
     */
    public function resumo(ReportFilters $f): array
    {
        $total = $this->baseQuery($f)->count();

        $semBairro = $this->baseQuery($f)
            ->whereNull('viability_requests.address_neighborhood')
            ->count();

        $bairros = $this->baseQuery($f)
            ->whereNotNull('viability_requests.address_neighborhood')
            ->distinct()
            ->count('viability_requests.address_neighborhood');

        return [
            'total' => $total,
            'bairros_distintos' => $bairros,
            'sem_bairro' => $semBairro,
        ];
    }

    /**
     * Distribuição por bairro com decisões e taxa de deferimento. As contagens
     * de deferidas/indeferidas usam `count(distinct ... case when)` sobre o
     * left join das decisões vinculantes (portável SQLite/PostgreSQL). A taxa é
     * deferidas ÷ (deferidas + indeferidas) — null sem decisões (CA-03). Bairro
     * nulo é excluído (entra só no resumo).
     *
     * @return array{degradacao: string, rotulo: string, itens: list<array{bairro: string, total: int, deferidas: int, indeferidas: int, taxa_deferimento: float|null}>}
     */
    public function porBairro(ReportFilters $f, int $limite = 100): array
    {
        $deferida = DecisionOutcome::Deferida->value;
        $indeferida = DecisionOutcome::Indeferida->value;

        $itens = $this->baseQuery($f)
            ->whereNotNull('viability_requests.address_neighborhood')
            ->leftJoin('viability_decisions as vd', 'vd.viability_request_id', '=', 'viability_requests.id')
            ->groupBy('bairro')
            ->orderByDesc('total')
            ->orderBy('bairro')
            ->limit($limite)
            ->get([
                'viability_requests.address_neighborhood as bairro',
                DB::raw('count(distinct viability_requests.id) as total'),
                DB::raw("count(distinct case when vd.outcome = '{$deferida}' then viability_requests.id end) as deferidas"),
                DB::raw("count(distinct case when vd.outcome = '{$indeferida}' then viability_requests.id end) as indeferidas"),
            ])
            ->map(function ($linha): array {
                $deferidas = (int) $linha->deferidas;
                $indeferidas = (int) $linha->indeferidas;
                $decididas = $deferidas + $indeferidas;

                return [
                    'bairro' => (string) $linha->bairro,
                    'total' => (int) $linha->total,
                    'deferidas' => $deferidas,
                    'indeferidas' => $indeferidas,
                    'taxa_deferimento' => $decididas > 0 ? round($deferidas / $decididas * 100, 1) : null,
                ];
            })
            ->all();

        return [
            'degradacao' => 'bairro',
            'rotulo' => 'Zona urbanística oficial indisponível (pendente SEDUR) — agrupado por bairro',
            'itens' => $itens,
        ];
    }

    /**
     * Builder base: população PROTOCOLADA filtrada pelos filtros comuns
     * (período/setor/analista/bairro/categoria/cnae) via `when()`, espelhando o
     * {@see IndicadoresViabilidadeService}. Colunas SEMPRE qualificadas para
     * conviver com o join das decisões.
     *
     * @return Builder<ViabilityRequest>
     */
    private function baseQuery(ReportFilters $f): Builder
    {
        return ViabilityRequest::query()
            ->whereNotNull('viability_requests.protocoled_at')
            ->when($f->from(), fn (Builder $q, $from): Builder => $q->where('viability_requests.protocoled_at', '>=', $from))
            ->when($f->to(), fn (Builder $q, $to): Builder => $q->where('viability_requests.protocoled_at', '<=', $to))
            ->when($f->setorId(), fn (Builder $q, int $id): Builder => $q->where('viability_requests.sector_id', $id))
            ->when($f->analistaId(), fn (Builder $q, int $id): Builder => $q->where('viability_requests.assigned_user_id', $id))
            ->when($f->bairro(), fn (Builder $q, string $b): Builder => $q->whereLike('viability_requests.address_neighborhood', "%{$b}%", caseSensitive: false))
            ->when($f->categoria(), fn (Builder $q, string $c): Builder => $this->aplicarCategoria($q, $c))
            ->when($f->cnae(), fn (Builder $q, string $cnae): Builder => $q->whereHas('cnaes', fn (Builder $c) => $c->where('cnaes.code', $cnae)));
    }

    /**
     * Filtro por categoria de consulta derivada (HU-082 RN-005), espelhando o
     * {@see IndicadoresViabilidadeService::aplicarCategoria()}.
     *
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    private function aplicarCategoria(Builder $query, string $categoria): Builder
    {
        return match ($categoria) {
            'malha_fina' => $query->where('viability_requests.in_fine_mesh', true),
            'sede_escritorio' => $query->where('viability_requests.is_virtual_office', true),
            'expresso' => $query->where('viability_requests.analysis_category', AnalysisCategory::Expresso->value),
            'semi_expresso' => $query->where('viability_requests.analysis_category', AnalysisCategory::SemiExpresso->value),
            default => $query,
        };
    }
}
