<?php

namespace App\Services\Relatorios\Bi;

use App\Enums\DecisionOutcome;
use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Inteligência econômica: o que as solicitações dizem sobre a procura por
 * atividades. Tudo por agregação SQL sobre as solicitações protocoladas, sem
 * dado pessoal.
 */
class EconomicoBi
{
    use ConsultasBi;

    /** Faixas de área pretendida (m²), na ordem de exibição. */
    private const FAIXAS_AREA = [
        'ate_50' => 'Até 50 m²',
        '51_100' => '51 a 100 m²',
        '101_250' => '101 a 250 m²',
        '251_500' => '251 a 500 m²',
        '501_1000' => '501 a 1.000 m²',
        'acima_1000' => 'Acima de 1.000 m²',
        'nao_informado' => 'Área não informada',
    ];

    /**
     * Ranking dos CNAEs mais solicitados, com o desfecho das decisões.
     *
     * @return list<array<string, mixed>>
     */
    public function ranking(BiConsulta $c): array
    {
        return $this->comPosicao(array_slice($this->cnaesAgregados($c), 0, $c->limite(20)));
    }

    /**
     * Atividades com maior taxa de indeferimento, só entre as que têm um mínimo
     * de decisões (evita 100% de uma única decisão).
     *
     * @return list<array<string, mixed>>
     */
    public function maiorIndeferimento(BiConsulta $c): array
    {
        $linhas = array_values(array_filter(
            $this->cnaesAgregados($c),
            fn (array $linha): bool => $linha['decididas'] >= max(1, $c->minDecididas),
        ));

        usort($linhas, fn (array $a, array $b): int => [$b['taxa_indeferimento'], $b['decididas']] <=> [$a['taxa_indeferimento'], $a['decididas']]);

        return $this->comPosicao(array_slice($linhas, 0, $c->limite(20)));
    }

    /**
     * Série de solicitações por CNAE nos CNAEs mais procurados do recorte.
     *
     * @return list<array{periodo: string, cnae: string, descricao: string|null, total: int}>
     */
    public function evolucao(BiConsulta $c): array
    {
        $descricoes = [];

        foreach (array_slice($this->cnaesAgregados($c), 0, $c->limite(10)) as $linha) {
            $descricoes[$linha['cnae']] = $linha['descricao'];
        }

        if ($descricoes === []) {
            return [];
        }

        $linhas = $this->solicitacoes($c->filtros)
            ->join('viability_request_cnaes as vrc', 'vrc.viability_request_id', '=', 'viability_requests.id')
            ->join('cnaes as c', 'c.id', '=', 'vrc.cnae_id')
            ->whereIn('c.code', array_keys($descricoes))
            ->groupBy('dia', 'c.code')
            ->toBase()
            ->get([
                DB::raw('date(viability_requests.protocoled_at) as dia'),
                'c.code as cnae',
                DB::raw('count(distinct viability_requests.id) as total'),
            ]);

        return array_map(
            fn (array $linha): array => [...$linha, 'descricao' => $descricoes[$linha['cnae']] ?? null],
            BiEstatistica::agrupar($linhas, $c->agrupamento, ['cnae'], ['total']),
        );
    }

    /**
     * CNAEs por bairro.
     *
     * @return list<array{bairro: string, cnae: string, descricao: string, total: int}>
     */
    public function cnaesPorBairro(BiConsulta $c): array
    {
        return $this->solicitacoes($c->filtros)
            ->whereNotNull(DB::raw(self::BAIRRO))
            ->join('viability_request_cnaes as vrc', 'vrc.viability_request_id', '=', 'viability_requests.id')
            ->join('cnaes as c', 'c.id', '=', 'vrc.cnae_id')
            ->groupBy('bairro', 'c.code', 'c.description')
            ->orderByDesc('total')
            ->orderBy('bairro')
            ->orderBy('c.code')
            ->limit($c->limite(500))
            ->toBase()
            ->get([
                DB::raw(self::BAIRRO.' as bairro'),
                'c.code as cnae',
                'c.description as descricao',
                DB::raw('count(distinct viability_requests.id) as total'),
            ])
            ->map(fn ($linha): array => [
                'bairro' => (string) $linha->bairro,
                'cnae' => (string) $linha->cnae,
                'descricao' => (string) $linha->descricao,
                'total' => (int) $linha->total,
            ])
            ->all();
    }

    /**
     * Solicitações por faixa de área pretendida.
     *
     * @return list<array{faixa: string, rotulo: string, total: int, deferidas: int, indeferidas: int}>
     */
    public function porPorteArea(BiConsulta $c): array
    {
        $area = 'viability_requests.used_area_m2';
        $faixa = "case when {$area} is null then 'nao_informado' "
            ."when {$area} <= 50 then 'ate_50' "
            ."when {$area} <= 100 then '51_100' "
            ."when {$area} <= 250 then '101_250' "
            ."when {$area} <= 500 then '251_500' "
            ."when {$area} <= 1000 then '501_1000' "
            ."else 'acima_1000' end";

        $totais = $this->solicitacoes($c->filtros)
            ->leftJoin('viability_decisions as vd', 'vd.viability_request_id', '=', 'viability_requests.id')
            ->groupByRaw($faixa)
            ->toBase()
            ->get([
                DB::raw("{$faixa} as faixa"),
                DB::raw('count(distinct viability_requests.id) as total'),
                ...$this->contagensDeDecisao(),
            ])
            ->keyBy(fn ($linha): string => (string) $linha->faixa);

        $linhas = [];

        foreach (self::FAIXAS_AREA as $chave => $rotulo) {
            $linha = $totais->get($chave);

            $linhas[] = [
                'faixa' => $chave,
                'rotulo' => $rotulo,
                'total' => (int) ($linha->total ?? 0),
                'deferidas' => (int) ($linha->deferidas ?? 0),
                'indeferidas' => (int) ($linha->indeferidas ?? 0),
            ];
        }

        return $linhas;
    }

    /**
     * Composição de risco por atividade (CNAE principal): o nível real do
     * Decreto 32.636/2020 quando há classificação; senão a categoria derivada;
     * senão "nao_classificado".
     *
     * @return list<array{cnae: string, descricao: string|null, nivel: string, fonte: string, total: int}>
     */
    public function atividadesPorRisco(BiConsulta $c): array
    {
        $versionId = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->value('id');

        if ($versionId !== null) {
            $nivel = "coalesce(rc.risco_municipal, viability_requests.analysis_category, 'nao_classificado')";
            $fonte = "case when rc.risco_municipal is not null then 'real' "
                ."when viability_requests.analysis_category is not null then 'derivada' else 'indefinida' end";
        } else {
            $nivel = "coalesce(viability_requests.analysis_category, 'nao_classificado')";
            $fonte = "case when viability_requests.analysis_category is not null then 'derivada' else 'indefinida' end";
        }

        $query = $this->solicitacoes($c->filtros)
            ->join('viability_request_cnaes as vrc', function (JoinClause $join): void {
                $join->on('vrc.viability_request_id', '=', 'viability_requests.id')
                    ->where('vrc.is_primary', true);
            })
            ->join('cnaes as c', 'c.id', '=', 'vrc.cnae_id');

        if ($versionId !== null) {
            $query->leftJoin('risk_classifications as rc', function (JoinClause $join) use ($versionId): void {
                $join->on('rc.cnae_code', '=', 'c.code')->where('rc.rule_version_id', $versionId);
            });
        }

        return $query
            ->groupByRaw("c.code, c.description, {$nivel}, {$fonte}")
            ->orderByRaw('total desc')
            ->orderBy('c.code')
            ->limit($c->limite(500))
            ->toBase()
            ->get([
                'c.code as cnae',
                'c.description as descricao',
                DB::raw("{$nivel} as nivel"),
                DB::raw("{$fonte} as fonte"),
                DB::raw('count(distinct viability_requests.id) as total'),
            ])
            ->map(fn ($linha): array => [
                'cnae' => (string) $linha->cnae,
                'descricao' => $linha->descricao !== null ? (string) $linha->descricao : null,
                'nivel' => (string) $linha->nivel,
                'fonte' => (string) $linha->fonte,
                'total' => (int) $linha->total,
            ])
            ->all();
    }

    /**
     * Todos os CNAEs do recorte, do mais ao menos solicitado.
     *
     * @return list<array<string, mixed>>
     */
    private function cnaesAgregados(BiConsulta $c): array
    {
        return $this->solicitacoes($c->filtros)
            ->join('viability_request_cnaes as vrc', 'vrc.viability_request_id', '=', 'viability_requests.id')
            ->join('cnaes as c', 'c.id', '=', 'vrc.cnae_id')
            ->leftJoin('viability_decisions as vd', 'vd.viability_request_id', '=', 'viability_requests.id')
            ->groupBy('c.code', 'c.description')
            ->orderByDesc('total')
            ->orderBy('c.code')
            ->toBase()
            ->get([
                'c.code as cnae',
                'c.description as descricao',
                DB::raw('count(distinct viability_requests.id) as total'),
                ...$this->contagensDeDecisao(),
            ])
            ->map(function ($linha): array {
                $deferidas = (int) $linha->deferidas;
                $indeferidas = (int) $linha->indeferidas;
                $decididas = $deferidas + $indeferidas;

                return [
                    'cnae' => (string) $linha->cnae,
                    'descricao' => (string) $linha->descricao,
                    'total' => (int) $linha->total,
                    'deferidas' => $deferidas,
                    'indeferidas' => $indeferidas,
                    'decididas' => $decididas,
                    'taxa_deferimento' => BiEstatistica::percentual($deferidas, $decididas),
                    'taxa_indeferimento' => BiEstatistica::percentual($indeferidas, $decididas),
                ];
            })
            ->all();
    }

    /**
     * @return list<Expression>
     */
    private function contagensDeDecisao(): array
    {
        $deferida = DecisionOutcome::Deferida->value;
        $indeferida = DecisionOutcome::Indeferida->value;

        return [
            DB::raw("count(distinct case when vd.outcome = '{$deferida}' then viability_requests.id end) as deferidas"),
            DB::raw("count(distinct case when vd.outcome = '{$indeferida}' then viability_requests.id end) as indeferidas"),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @return list<array<string, mixed>>
     */
    private function comPosicao(array $linhas): array
    {
        return array_map(
            fn (array $linha, int $i): array => ['posicao' => $i + 1, ...$linha],
            $linhas,
            array_keys($linhas),
        );
    }
}
