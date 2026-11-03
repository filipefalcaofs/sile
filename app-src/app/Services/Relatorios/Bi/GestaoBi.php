<?php

namespace App\Services\Relatorios\Bi;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestOrigin;
use App\Models\ViabilityDecision;
use App\Services\Relatorios\DistanciaBenchmarkService;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Leituras de gestão para o secretário e os diretores: como o período se
 * compara com o anterior e com o ano passado, quanto Salvador leva frente à
 * REDESIM, que negócios a cidade está atraindo e onde a regra urbanística mais
 * barra atividades.
 */
class GestaoBi
{
    use ConsultasBi;

    public function __construct(private readonly DistanciaBenchmarkService $benchmark) {}

    /**
     * Indicadores do período lado a lado com o período anterior de mesmo tamanho
     * e com o mesmo período do ano passado. Sem período informado, vale os
     * últimos 30 dias. `melhor_quando` diz se subir é bom ou ruim.
     *
     * @return array<string, mixed>
     */
    public function comparativo(BiConsulta $c): array
    {
        [$de, $ate] = $this->janela($c->filtros);
        $dias = (int) $de->copy()->startOfDay()->diffInDays($ate->copy()->startOfDay()) + 1;

        $periodos = [
            'atual' => [$de, $ate],
            'anterior' => [$de->copy()->subDays($dias)->startOfDay(), $de->copy()->subDay()->endOfDay()],
            'ano_anterior' => [$de->copy()->subYearNoOverflow(), $ate->copy()->subYearNoOverflow()],
        ];

        $valores = [];

        foreach ($periodos as $chave => [$inicio, $fim]) {
            $valores[$chave] = $this->metricas($this->recorte($c->filtros, $inicio, $fim));
        }

        $rotulos = [
            'protocoladas' => ['Solicitações protocoladas', 'maior'],
            'decididas' => ['Decisões', 'maior'],
            'deferidas' => ['Deferidas', 'maior'],
            'indeferidas' => ['Indeferidas', 'menor'],
            'taxa_deferimento' => ['Taxa de deferimento (%)', 'maior'],
            'tempo_medio_horas_uteis' => ['Tempo médio de viabilidade (horas úteis)', 'menor'],
            'tempo_mediano_horas_uteis' => ['Tempo mediano de viabilidade (horas úteis)', 'menor'],
            'taxa_automacao' => ['Taxa de automação (%)', 'maior'],
            'empresas_viabilizadas' => ['Empresas viabilizadas', 'maior'],
            'area_liberada_m2' => ['Área liberada (m²)', 'maior'],
        ];

        $indicadores = [];

        foreach ($rotulos as $chave => [$rotulo, $melhor]) {
            $atual = $valores['atual'][$chave];
            $anterior = $valores['anterior'][$chave];
            $anoAnterior = $valores['ano_anterior'][$chave];

            $indicadores[] = [
                'indicador' => $chave,
                'rotulo' => $rotulo,
                'melhor_quando' => $melhor,
                'atual' => $atual,
                'anterior' => $anterior,
                'ano_anterior' => $anoAnterior,
                'variacao_anterior_percentual' => $this->variacao($atual, $anterior),
                'variacao_ano_percentual' => $this->variacao($atual, $anoAnterior),
            ];
        }

        return [
            'periodos' => array_map(fn (array $p): array => ['de' => $p[0]->toDateString(), 'ate' => $p[1]->toDateString()], $periodos),
            'indicadores' => $indicadores,
        ];
    }

    /**
     * Tempo de viabilidade de Salvador no Viabiliza frente à apuração da REDESIM,
     * no período e mês a mês.
     *
     * @return array{periodo: array<string, mixed>, mes_corrente: array<string, mixed>}
     */
    public function benchmarkRedesim(BiConsulta $c): array
    {
        return [
            'periodo' => $this->benchmark->calcular($c->filtros),
            'mes_corrente' => $this->benchmark->evolucaoMensal(Carbon::now()),
        ];
    }

    /**
     * Que pedidos chegam: por serviço da JUCEB, tipo de serviço, canal de
     * entrada, porte da empresa e escritório virtual, cada um com o desfecho.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function perfilDemanda(BiConsulta $c): array
    {
        $origens = [];

        foreach ($this->agrupar($c, 'viability_requests.origin') as $linha) {
            $origem = ViabilityRequestOrigin::tryFrom((string) $linha['chave']);
            $origens[] = ['origem' => $linha['chave'], 'rotulo' => $origem?->label() ?? (string) $linha['chave'], ...$this->semChave($linha)];
        }

        $escritorio = array_map(fn (array $linha): array => [
            'escritorio_virtual' => (bool) $linha['chave'],
            ...$this->semChave($linha),
        ], $this->agrupar($c, 'viability_requests.is_virtual_office'));

        return [
            'por_servico' => $this->renomear($this->agrupar(
                $c,
                'ev.nome',
                fn (Builder $q): Builder => $q->join('viability_request_eventos as ev', 'ev.viability_request_id', '=', 'viability_requests.id'),
            ), 'servico'),
            'por_tipo_servico' => $this->renomear($this->agrupar(
                $c,
                'st.name',
                fn (Builder $q): Builder => $q->join('viability_service_types as st', 'st.id', '=', 'viability_requests.service_type_id'),
            ), 'tipo_servico'),
            'por_origem' => $origens,
            'por_porte' => $this->renomear($this->agrupar(
                $c,
                "coalesce(co.size, 'Não informado')",
                fn (Builder $q): Builder => $q->leftJoin('companies as co', 'co.id', '=', 'viability_requests.company_id'),
            ), 'porte'),
            'escritorio_virtual' => $escritorio,
        ];
    }

    /**
     * Resultado econômico das viabilidades deferidas no período: empresas, área
     * liberada e porte, com a série por período.
     *
     * @return array<string, mixed>
     */
    public function impactoEconomico(BiConsulta $c): array
    {
        $base = fn (): Builder => $this->decisoes($c->filtros)
            ->where('viability_decisions.outcome', DecisionOutcome::Deferida->value);

        $totais = $base()
            ->toBase()
            ->first([
                DB::raw('count(*) as deferidas'),
                DB::raw('count(distinct viability_requests.company_id) as empresas'),
                DB::raw('sum(viability_requests.used_area_m2) as area'),
                DB::raw('count(viability_requests.used_area_m2) as com_area'),
            ]);

        $area = (float) ($totais->area ?? 0);
        $comArea = (int) ($totais->com_area ?? 0);

        $porPorte = $base()
            ->leftJoin('companies as co', 'co.id', '=', 'viability_requests.company_id')
            ->groupByRaw("coalesce(co.size, 'Não informado')")
            ->orderByDesc('deferidas')
            ->toBase()
            ->get([
                DB::raw("coalesce(co.size, 'Não informado') as porte"),
                DB::raw('count(*) as deferidas'),
                DB::raw('count(distinct viability_requests.company_id) as empresas'),
                DB::raw('sum(viability_requests.used_area_m2) as area'),
            ])
            ->map(fn ($linha): array => [
                'porte' => (string) $linha->porte,
                'deferidas' => (int) $linha->deferidas,
                'empresas' => (int) $linha->empresas,
                'area_liberada_m2' => round((float) ($linha->area ?? 0), 2),
            ])
            ->all();

        $serie = BiEstatistica::agrupar(
            $base()
                ->groupBy('dia')
                ->toBase()
                ->get([
                    DB::raw('date(viability_decisions.decided_at) as dia'),
                    DB::raw('count(*) as deferidas'),
                    DB::raw('coalesce(sum(viability_requests.used_area_m2), 0) as area'),
                ])
                ->map(fn ($linha): object => (object) ['dia' => $linha->dia, 'deferidas' => $linha->deferidas, 'area' => (int) round((float) $linha->area)]),
            $c->agrupamento,
            [],
            ['deferidas', 'area'],
        );

        return [
            'deferidas' => (int) ($totais->deferidas ?? 0),
            'empresas_viabilizadas' => (int) ($totais->empresas ?? 0),
            'area_liberada_m2' => round($area, 2),
            'area_media_m2' => $comArea > 0 ? round($area / $comArea, 2) : null,
            'deferidas_sem_area_informada' => (int) ($totais->deferidas ?? 0) - $comArea,
            'por_porte' => $porPorte,
            'serie' => array_map(fn (array $linha): array => [
                'periodo' => $linha['periodo'],
                'deferidas' => $linha['deferidas'],
                'area_liberada_m2' => $linha['area'],
            ], $serie),
        ];
    }

    /**
     * Onde a regra urbanística mais barra atividades: zona × CNAE com o volume
     * decidido, os indeferimentos e o fundamento mais citado neles. Só entram os
     * pares com ao menos `min_decididas` decisões.
     *
     * @return list<array<string, mixed>>
     */
    public function indeferimentosZonaAtividade(BiConsulta $c): array
    {
        $deferida = DecisionOutcome::Deferida->value;
        $indeferida = DecisionOutcome::Indeferida->value;

        $pares = $this->decisoes($c->filtros)
            ->whereNotNull('viability_requests.zona_codigo')
            ->join('viability_request_cnaes as vrc', 'vrc.viability_request_id', '=', 'viability_requests.id')
            ->join('cnaes as c', 'c.id', '=', 'vrc.cnae_id')
            ->groupBy('viability_requests.zona_codigo', 'c.code', 'c.description')
            ->toBase()
            ->get([
                'viability_requests.zona_codigo as zona',
                'c.code as cnae',
                'c.description as descricao',
                DB::raw('count(distinct viability_requests.id) as decididas'),
                DB::raw("count(distinct case when viability_decisions.outcome = '{$deferida}' then viability_requests.id end) as deferidas"),
                DB::raw("count(distinct case when viability_decisions.outcome = '{$indeferida}' then viability_requests.id end) as indeferidas"),
            ])
            ->filter(fn ($linha): bool => (int) $linha->decididas >= max(1, $c->minDecididas) && (int) $linha->indeferidas > 0);

        if ($pares->isEmpty()) {
            return [];
        }

        $fundamentos = $this->fundamentosPorZonaCnae($c->filtros);

        $linhas = $pares->map(function ($linha) use ($fundamentos): array {
            $citados = $fundamentos[$linha->zona.'|'.$linha->cnae] ?? [];
            arsort($citados);

            return [
                'zona' => (string) $linha->zona,
                'cnae' => (string) $linha->cnae,
                'descricao' => (string) $linha->descricao,
                'decididas' => (int) $linha->decididas,
                'deferidas' => (int) $linha->deferidas,
                'indeferidas' => (int) $linha->indeferidas,
                'taxa_indeferimento' => BiEstatistica::percentual((int) $linha->indeferidas, (int) $linha->deferidas + (int) $linha->indeferidas),
                'fundamento_principal' => array_key_first($citados),
            ];
        })->values()->all();

        usort($linhas, fn (array $a, array $b): int => [$b['indeferidas'], $b['taxa_indeferimento']] <=> [$a['indeferidas'], $a['taxa_indeferimento']]);

        return array_map(
            fn (array $linha, int $i): array => ['posicao' => $i + 1, ...$linha],
            array_slice($linhas, 0, $c->limite(50)),
            array_keys(array_slice($linhas, 0, $c->limite(50))),
        );
    }

    /**
     * @return array{protocoladas: int, decididas: int, deferidas: int, indeferidas: int, taxa_deferimento: float|null, tempo_medio_horas_uteis: float|null, tempo_mediano_horas_uteis: float|null, taxa_automacao: float|null, empresas_viabilizadas: int, area_liberada_m2: float}
     */
    private function metricas(ReportFilters $f): array
    {
        $decisoes = $this->decisoes($f)
            ->toBase()
            ->first([
                DB::raw('count(*) as decididas'),
                DB::raw("sum(case when viability_decisions.outcome = '".DecisionOutcome::Deferida->value."' then 1 else 0 end) as deferidas"),
                DB::raw("sum(case when viability_decisions.outcome = '".DecisionOutcome::Indeferida->value."' then 1 else 0 end) as indeferidas"),
                DB::raw("sum(case when viability_decisions.flow = 'expresso' then 1 else 0 end) as automaticas"),
                DB::raw("count(distinct case when viability_decisions.outcome = '".DecisionOutcome::Deferida->value."' then viability_requests.company_id end) as empresas"),
                DB::raw("sum(case when viability_decisions.outcome = '".DecisionOutcome::Deferida->value."' then viability_requests.used_area_m2 else 0 end) as area"),
            ]);

        $decididas = (int) ($decisoes->decididas ?? 0);
        $tempo = BiEstatistica::resumo($this->temposViabilidade($f));

        return [
            'protocoladas' => $this->solicitacoes($f)->count(),
            'decididas' => $decididas,
            'deferidas' => (int) ($decisoes->deferidas ?? 0),
            'indeferidas' => (int) ($decisoes->indeferidas ?? 0),
            'taxa_deferimento' => BiEstatistica::percentual((int) ($decisoes->deferidas ?? 0), $decididas),
            'tempo_medio_horas_uteis' => $tempo['media'],
            'tempo_mediano_horas_uteis' => $tempo['mediana'],
            'taxa_automacao' => BiEstatistica::percentual((int) ($decisoes->automaticas ?? 0), $decididas),
            'empresas_viabilizadas' => (int) ($decisoes->empresas ?? 0),
            'area_liberada_m2' => round((float) ($decisoes->area ?? 0), 2),
        ];
    }

    /**
     * Contagem por uma expressão de agrupamento sobre as solicitações do período,
     * com o desfecho das decisões.
     *
     * @param  (callable(Builder): Builder)|null  $juntar
     * @return list<array{chave: mixed, total: int, deferidas: int, indeferidas: int, taxa_deferimento: float|null}>
     */
    private function agrupar(BiConsulta $c, string $expressao, ?callable $juntar = null): array
    {
        $deferida = DecisionOutcome::Deferida->value;
        $indeferida = DecisionOutcome::Indeferida->value;

        $query = $this->solicitacoes($c->filtros)
            ->leftJoin('viability_decisions as vd', 'vd.viability_request_id', '=', 'viability_requests.id');

        if ($juntar !== null) {
            $query = $juntar($query);
        }

        return $query
            ->groupByRaw($expressao)
            ->orderByRaw('total desc')
            ->limit($c->limite(100))
            ->toBase()
            ->get([
                DB::raw("{$expressao} as chave"),
                DB::raw('count(distinct viability_requests.id) as total'),
                DB::raw("count(distinct case when vd.outcome = '{$deferida}' then viability_requests.id end) as deferidas"),
                DB::raw("count(distinct case when vd.outcome = '{$indeferida}' then viability_requests.id end) as indeferidas"),
            ])
            ->map(fn ($linha): array => [
                'chave' => $linha->chave,
                'total' => (int) $linha->total,
                'deferidas' => (int) $linha->deferidas,
                'indeferidas' => (int) $linha->indeferidas,
                'taxa_deferimento' => BiEstatistica::percentual((int) $linha->deferidas, (int) $linha->deferidas + (int) $linha->indeferidas),
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @return list<array<string, mixed>>
     */
    private function renomear(array $linhas, string $nome): array
    {
        return array_map(fn (array $linha): array => [$nome => (string) $linha['chave'], ...$this->semChave($linha)], $linhas);
    }

    /**
     * @param  array<string, mixed>  $linha
     * @return array<string, mixed>
     */
    private function semChave(array $linha): array
    {
        unset($linha['chave']);

        return $linha;
    }

    /**
     * Fundamentos citados nos indeferimentos, contados por zona|cnae.
     *
     * @return array<string, array<string, int>>
     */
    private function fundamentosPorZonaCnae(ReportFilters $f): array
    {
        $indeferidas = [];

        $decisoes = $this->decisoes($f)
            ->where('viability_decisions.outcome', DecisionOutcome::Indeferida->value)
            ->whereNotNull('viability_requests.zona_codigo')
            ->select('viability_decisions.id', 'viability_decisions.fundamentacao', 'viability_requests.id as processo_id', 'viability_requests.zona_codigo as zona')
            ->lazyById(500, 'viability_decisions.id', 'id');

        /** @var ViabilityDecision $decisao */
        foreach ($decisoes as $decisao) {
            $indeferidas[(int) $decisao->getAttribute('processo_id')] = [
                'zona' => (string) $decisao->getAttribute('zona'),
                'fundamentos' => array_unique(array_filter((array) $decisao->fundamentacao, 'is_string')),
            ];
        }

        $contagem = [];

        foreach (array_chunk(array_keys($indeferidas), 1000) as $ids) {
            $cnaes = DB::table('viability_request_cnaes as vrc')
                ->join('cnaes as c', 'c.id', '=', 'vrc.cnae_id')
                ->whereIn('vrc.viability_request_id', $ids)
                ->get(['vrc.viability_request_id as id', 'c.code as codigo']);

            foreach ($cnaes as $cnae) {
                $processo = $indeferidas[(int) $cnae->id];

                foreach ($processo['fundamentos'] as $fundamento) {
                    $chave = $processo['zona'].'|'.$cnae->codigo;
                    $contagem[$chave][$fundamento] = ($contagem[$chave][$fundamento] ?? 0) + 1;
                }
            }
        }

        return $contagem;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function janela(ReportFilters $f): array
    {
        $ate = $f->to() ?? Carbon::now()->endOfDay();
        $de = $f->from() ?? $ate->copy()->subDays(29)->startOfDay();

        return [$de, $ate];
    }

    private function recorte(ReportFilters $f, Carbon $de, Carbon $ate): ReportFilters
    {
        return ReportFilters::fromArray([
            ...$f->toArray(),
            'data_de' => $de->toDateString(),
            'data_ate' => $ate->toDateString(),
        ]);
    }

    private function variacao(int|float|null $atual, int|float|null $referencia): ?float
    {
        if ($atual === null || $referencia === null || (float) $referencia === 0.0) {
            return null;
        }

        return round(($atual - $referencia) / $referencia * 100, 1);
    }
}
