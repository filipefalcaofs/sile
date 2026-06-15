<?php

namespace App\Services\Relatorios;

use App\Enums\AnalysisCategory;
use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Analise\ProcessoQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores de solicitações de viabilidade (HU-123 a HU-128) por AGREGAÇÃO
 * SQL real sobre os dados das Fases 8–10, espelhando o estilo do
 * {@see ProcessoQueryService}: Builder + `when()` (o
 * filtro só entra quando informado) + `groupBy`/`selectRaw`/`DB::raw`, NUNCA
 * loop PHP sobre coleção carregada (regra de ouro do Pattern 1 do RESEARCH).
 *
 * Route-free e sem estado: recebe um {@see ReportFilters} e devolve arrays
 * prontos para a UI/export. Toda degradação é HONESTA (entrega funcional sem
 * fachada): a zona urbanística oficial está bloqueada na SEDUR, então a HU-124
 * agrupa por bairro com rótulo explícito; o risco prefere o nível real do
 * Decreto 32.636/2020 e só então cai na categoria derivada ou em "não
 * classificado"; período sem dados devolve lista vazia, jamais um número
 * inventado (CA-03).
 *
 * População dos indicadores = solicitações PROTOCOLADAS (protocoled_at não
 * nulo); rascunhos não são submissões e ficam fora das métricas gerenciais.
 */
class IndicadoresViabilidadeService
{
    /**
     * Solicitações protocoladas por dia no intervalo (HU-123). A data é truncada
     * em SQL com `date()` (portável SQLite/PostgreSQL — verificado) e agrupada
     * por dia. Sem dados no recorte → lista vazia (anti-fachada, CA-03).
     *
     * @return list<array{dia: string, total: int}>
     */
    public function porPeriodo(ReportFilters $f): array
    {
        return $this->baseQuery($f)
            ->groupBy('dia')
            ->orderBy('dia')
            ->get([
                DB::raw('date(protocoled_at) as dia'),
                DB::raw('count(*) as total'),
            ])
            ->map(fn ($linha): array => [
                'dia' => (string) $linha->dia,
                'total' => (int) $linha->total,
            ])
            ->all();
    }

    /**
     * Solicitações por zona urbanística — DEGRADADO para bairro (HU-124). A zona
     * oficial (Quadro LOUOS/GIS) está bloqueada na SEDUR; em vez de inventar uma
     * zona, agrupa por bairro com rótulo de degradação honesto. Solicitações sem
     * bairro informado não viram bucket fantasma.
     *
     * @return array{degradacao: string, rotulo: string, itens: list<array{bairro: string, total: int}>}
     */
    public function porZona(ReportFilters $f): array
    {
        $itens = $this->baseQuery($f)
            ->whereNotNull('viability_requests.address_neighborhood')
            ->groupBy('bairro')
            ->orderByDesc('total')
            ->orderBy('bairro')
            ->get([
                'viability_requests.address_neighborhood as bairro',
                DB::raw('count(*) as total'),
            ])
            ->map(fn ($linha): array => [
                'bairro' => (string) $linha->bairro,
                'total' => (int) $linha->total,
            ])
            ->all();

        return [
            'degradacao' => 'bairro',
            'rotulo' => 'Zona urbanística indisponível — agrupado por bairro',
            'itens' => $itens,
        ];
    }

    /**
     * Solicitações por CNAE (HU-125): join na tabela pivô + cnaes, contando
     * processos DISTINTOS por código de CNAE; top N ordenado desc. Agregação em
     * SQL (count distinct), nunca em coleção.
     *
     * @return list<array{cnae: string, total: int}>
     */
    public function porCnae(ReportFilters $f, int $limite = 10): array
    {
        return $this->baseQuery($f)
            ->join('viability_request_cnaes as vrc', 'vrc.viability_request_id', '=', 'viability_requests.id')
            ->join('cnaes as c', 'c.id', '=', 'vrc.cnae_id')
            ->groupBy('c.code')
            ->orderByDesc('total')
            ->orderBy('c.code')
            ->limit($limite)
            ->get([
                'c.code as cnae',
                DB::raw('count(distinct viability_requests.id) as total'),
            ])
            ->map(fn ($linha): array => [
                'cnae' => (string) $linha->cnae,
                'total' => (int) $linha->total,
            ])
            ->all();
    }

    /**
     * Solicitações por nível de risco (HU-126), PREFERINDO o nível REAL do
     * Decreto 32.636/2020: join do CNAE principal com a classificação municipal
     * da versão VIGENTE. Sem classificação do CNAE, cai no FALLBACK da categoria
     * derivada (analysis_category consolidada na Fase 10); na ausência dela,
     * rotula "nao_classificado" (honesto). NUNCA inventa nível. Cada linha
     * carrega a `fonte` (real|derivada|indefinida) para transparência.
     *
     * Agregação inteiramente em SQL (count distinct + group by sobre as
     * expressões `coalesce`/`case`), portável SQLite/PostgreSQL (verificado).
     *
     * @return list<array{nivel: string, fonte: string, total: int}>
     */
    public function porRisco(ReportFilters $f): array
    {
        $versionId = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->value('id');

        // Cadeia de fallback honesta: risco real → categoria derivada → não
        // classificado. Sem versão vigente, o risco real é impossível e a
        // expressão nem referencia a tabela de classificação.
        if ($versionId !== null) {
            $nivelExpr = "coalesce(rc.risco_municipal, viability_requests.analysis_category, 'nao_classificado')";
            $fonteExpr = "case when rc.risco_municipal is not null then 'real' "
                ."when viability_requests.analysis_category is not null then 'derivada' "
                ."else 'indefinida' end";
        } else {
            $nivelExpr = "coalesce(viability_requests.analysis_category, 'nao_classificado')";
            $fonteExpr = "case when viability_requests.analysis_category is not null then 'derivada' else 'indefinida' end";
        }

        $query = $this->baseQuery($f)
            ->leftJoin('viability_request_cnaes as vrc', function (JoinClause $join): void {
                $join->on('vrc.viability_request_id', '=', 'viability_requests.id')
                    ->where('vrc.is_primary', true);
            })
            ->leftJoin('cnaes as c', 'c.id', '=', 'vrc.cnae_id');

        if ($versionId !== null) {
            $query->leftJoin('risk_classifications as rc', function (JoinClause $join) use ($versionId): void {
                $join->on('rc.cnae_code', '=', 'c.code')
                    ->where('rc.rule_version_id', $versionId);
            });
        }

        return $query
            ->groupByRaw("{$nivelExpr}, {$fonteExpr}")
            ->orderByRaw('total desc')
            ->get([
                DB::raw("{$nivelExpr} as nivel"),
                DB::raw("{$fonteExpr} as fonte"),
                DB::raw('count(distinct viability_requests.id) as total'),
            ])
            ->map(fn ($linha): array => [
                'nivel' => (string) $linha->nivel,
                'fonte' => (string) $linha->fonte,
                'total' => (int) $linha->total,
            ])
            ->all();
    }

    /**
     * Builder base dos indicadores de solicitação: população PROTOCOLADA filtrada
     * pelos filtros comuns (período/setor/analista/bairro/categoria/cnae) via
     * `when()`, espelhando o ProcessoQueryService. Colunas SEMPRE qualificadas
     * (`viability_requests.*`) para conviver com os joins dos indicadores.
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
     * Filtro por categoria de consulta DERIVADA (HU-082 RN-005), espelhando o
     * ProcessoQueryService. Colunas qualificadas para uso com ou sem join.
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
