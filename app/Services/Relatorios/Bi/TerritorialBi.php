<?php

namespace App\Services\Relatorios\Bi;

use App\Enums\DecisionOutcome;
use App\Models\ViabilityDecision;
use App\Services\Relatorios\GeoBairroIndicadorService;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\SaturacaoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Inteligência territorial: onde está a demanda, como ela é decidida e o que a
 * restringe. Os pontos do mapa saem em células de grade (sem coordenada exata
 * de imóvel), e nada aqui identifica pessoa ou empresa.
 */
class TerritorialBi
{
    use ConsultasBi;

    public function __construct(
        private readonly GeoBairroIndicadorService $geo,
        private readonly SaturacaoService $saturacao,
        private readonly EconomicoBi $economico,
    ) {}

    /**
     * Concentração de solicitações em células de grade (`precisao` casas
     * decimais: 3 ≈ 110 m).
     *
     * @return list<array{lat: float, lng: float, total: int}>
     */
    public function mapaConcentracao(BiConsulta $c): array
    {
        return array_map(
            fn (array $celula): array => ['lat' => $celula['lat'], 'lng' => $celula['lng'], 'total' => $celula['total']],
            $this->celulas($c, detalhado: false),
        );
    }

    /**
     * Deferimentos, indeferimentos e perfil da área por célula de grade.
     *
     * @return list<array<string, mixed>>
     */
    public function mapaDecisoes(BiConsulta $c): array
    {
        return $this->celulas($c);
    }

    /**
     * @return array{resumo: array{com_zona: int, sem_zona: int}, itens: list<array<string, mixed>>}
     */
    public function porZona(BiConsulta $c): array
    {
        return $this->geo->porZona($c->filtros, $c->limite(100));
    }

    /**
     * Os CNAEs que mais aparecem em cada bairro (vocação econômica indicada).
     *
     * @return list<array{bairro: string, posicao: int, cnae: string, descricao: string, total: int, participacao: float|null}>
     */
    public function cnaesPredominantes(BiConsulta $c): array
    {
        $porBairro = [];

        foreach ($this->economico->cnaesPorBairro(new BiConsulta($c->filtros, limite: 100000)) as $linha) {
            $porBairro[$linha['bairro']][] = $linha;
        }

        ksort($porBairro);
        $topo = max(1, $c->limite(3));
        $linhas = [];

        foreach ($porBairro as $bairro => $cnaes) {
            $soma = array_sum(array_column($cnaes, 'total'));

            foreach (array_slice($cnaes, 0, $topo) as $i => $cnae) {
                $linhas[] = [
                    'bairro' => (string) $bairro,
                    'posicao' => $i + 1,
                    'cnae' => $cnae['cnae'],
                    'descricao' => $cnae['descricao'],
                    'total' => $cnae['total'],
                    'participacao' => BiEstatistica::percentual($cnae['total'], $soma),
                ];
            }
        }

        return $linhas;
    }

    /**
     * Fundamentos legais mais citados nas decisões do período e quanto cada um
     * pesa nos indeferimentos.
     *
     * @return list<array{posicao: int, fundamento: string, decisoes: int, indeferidas: int, deferidas: int, participacao_indeferimentos: float|null}>
     */
    public function restricoes(BiConsulta $c): array
    {
        $citacoes = [];
        $totalIndeferidas = 0;

        $decisoes = $this->decisoes($c->filtros)
            ->select('viability_decisions.id', 'viability_decisions.outcome', 'viability_decisions.fundamentacao')
            ->lazyById(500, 'viability_decisions.id', 'id');

        /** @var ViabilityDecision $decisao */
        foreach ($decisoes as $decisao) {
            $indeferida = $decisao->outcome === DecisionOutcome::Indeferida;
            $totalIndeferidas += $indeferida ? 1 : 0;

            foreach (array_unique(array_filter((array) $decisao->fundamentacao, 'is_string')) as $fundamento) {
                $citacoes[$fundamento] ??= ['decisoes' => 0, 'indeferidas' => 0, 'deferidas' => 0];
                $citacoes[$fundamento]['decisoes']++;
                $citacoes[$fundamento]['indeferidas'] += $indeferida ? 1 : 0;
                $citacoes[$fundamento]['deferidas'] += $decisao->outcome === DecisionOutcome::Deferida ? 1 : 0;
            }
        }

        uasort($citacoes, fn (array $a, array $b): int => [$b['indeferidas'], $b['decisoes']] <=> [$a['indeferidas'], $a['decisoes']]);

        $linhas = [];

        foreach (array_slice($citacoes, 0, $c->limite(20), true) as $fundamento => $dados) {
            $linhas[] = [
                'posicao' => count($linhas) + 1,
                'fundamento' => (string) $fundamento,
                ...$dados,
                'participacao_indeferimentos' => BiEstatistica::percentual($dados['indeferidas'], $totalIndeferidas),
            ];
        }

        return $linhas;
    }

    /**
     * Saturação locacional por bairro e CNAE (saturando e saturado em destaque).
     *
     * @return array{limiares: array{alerta: float, bloqueio: float}, resumo: array<string, int>, itens: list<array<string, mixed>>}
     */
    public function saturacao(BiConsulta $c): array
    {
        $itens = $this->saturacao->porBairroCnae($c->filtros, $c->limite(500));

        if ($c->situacao !== null) {
            $itens = array_values(array_filter($itens, fn (array $linha): bool => $linha['situacao'] === $c->situacao));
        }

        return [
            'limiares' => $this->saturacao->limiares(),
            'resumo' => $this->saturacao->resumo($c->filtros),
            'itens' => $itens,
        ];
    }

    /**
     * Solicitações por bairro em cada período (os bairros com mais demanda).
     *
     * @return list<array{periodo: string, bairro: string, total: int}>
     */
    public function evolucao(BiConsulta $c): array
    {
        $bairros = array_column($this->geo->porBairro($c->filtros, $c->limite(10))['itens'], 'bairro');

        if ($bairros === []) {
            return [];
        }

        $linhas = $this->solicitacoes($c->filtros)
            ->whereIn(DB::raw(self::BAIRRO), $bairros)
            ->groupBy('dia', 'bairro')
            ->toBase()
            ->get([
                DB::raw('date(viability_requests.protocoled_at) as dia'),
                DB::raw(self::BAIRRO.' as bairro'),
                DB::raw('count(*) as total'),
            ]);

        return BiEstatistica::agrupar($linhas, $c->agrupamento, ['bairro'], ['total']);
    }

    /**
     * Comparação da demanda por bairro com o período imediatamente anterior, do
     * mesmo tamanho. Sem período informado, vale os últimos 30 dias.
     *
     * @return array{atual: array{de: string, ate: string}, anterior: array{de: string, ate: string}, itens: list<array<string, mixed>>}
     */
    public function comparativo(BiConsulta $c): array
    {
        $ate = $c->filtros->to() ?? Carbon::now()->endOfDay();
        $de = $c->filtros->from() ?? $ate->copy()->subDays(29)->startOfDay();
        $dias = max(1, (int) $de->copy()->startOfDay()->diffInDays($ate->copy()->startOfDay()) + 1);
        $anteriorAte = $de->copy()->subSecond();
        $anteriorDe = $de->copy()->subDays($dias)->startOfDay();

        $atual = $this->totaisPorBairro($c, $de, $ate);
        $anterior = $this->totaisPorBairro($c, $anteriorDe, $anteriorAte);

        $itens = [];

        foreach (array_unique([...array_keys($atual), ...array_keys($anterior)]) as $bairro) {
            $agora = $atual[$bairro] ?? 0;
            $antes = $anterior[$bairro] ?? 0;

            $itens[] = [
                'bairro' => (string) $bairro,
                'atual' => $agora,
                'anterior' => $antes,
                'variacao' => $agora - $antes,
                'variacao_percentual' => $antes > 0 ? round(($agora - $antes) / $antes * 100, 1) : null,
            ];
        }

        usort($itens, fn (array $a, array $b): int => [$b['atual'], $a['bairro']] <=> [$a['atual'], $b['bairro']]);

        return [
            'atual' => ['de' => $de->toDateString(), 'ate' => $ate->toDateString()],
            'anterior' => ['de' => $anteriorDe->toDateString(), 'ate' => $anteriorAte->toDateString()],
            'itens' => array_slice($itens, 0, $c->limite(100)),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function totaisPorBairro(BiConsulta $c, Carbon $de, Carbon $ate): array
    {
        $filtros = ReportFilters::fromArray([
            ...$c->filtros->toArray(),
            'data_de' => $de->toDateString(),
            'data_ate' => $ate->toDateString(),
        ]);

        return $this->solicitacoes($filtros)
            ->whereNotNull(DB::raw(self::BAIRRO))
            ->groupBy('bairro')
            ->toBase()
            ->get([DB::raw(self::BAIRRO.' as bairro'), DB::raw('count(*) as total')])
            ->mapWithKeys(fn ($linha): array => [(string) $linha->bairro => (int) $linha->total])
            ->all();
    }

    /**
     * Células de grade com o desfecho das decisões e o perfil da área: situação
     * em aberto, bairro e zona predominantes, CNAEs mais frequentes (com a
     * saturação do par bairro × CNAE), fundamentos mais citados nos
     * indeferimentos, tempo médio e, com período informado, a tendência frente
     * ao período anterior de mesmo tamanho. Não identifica processo, empresa nem
     * pessoa. Sem `detalhado`, só as contagens.
     *
     * @return list<array<string, mixed>>
     */
    private function celulas(BiConsulta $c, bool $detalhado = true): array
    {
        $precisao = min(5, max(2, $c->precisao));
        $deferida = DecisionOutcome::Deferida->value;
        $indeferida = DecisionOutcome::Indeferida->value;
        $celulas = [];
        $celulaDoProcesso = [];

        $pontos = $this->solicitacoes($c->filtros)
            ->whereNotNull('viability_requests.property_point_lat')
            ->whereNotNull('viability_requests.property_point_lng')
            ->leftJoin('viability_decisions as vd', 'vd.viability_request_id', '=', 'viability_requests.id')
            ->toBase()
            ->select(
                'viability_requests.id as id',
                'viability_requests.property_point_lat as lat',
                'viability_requests.property_point_lng as lng',
                'viability_requests.status as status',
                'viability_requests.zona_codigo as zona',
                'viability_requests.viabilidade_horas_uteis as horas',
                DB::raw(self::BAIRRO.' as bairro'),
                'vd.outcome as outcome',
                'vd.fundamentacao as fundamentacao',
            )
            ->cursor();

        foreach ($pontos as $ponto) {
            $lat = round((float) $ponto->lat, $precisao);
            $lng = round((float) $ponto->lng, $precisao);
            $chave = $lat.'|'.$lng;

            $celulas[$chave] ??= [
                'lat' => $lat, 'lng' => $lng, 'total' => 0, 'deferidas' => 0, 'indeferidas' => 0, 'em_analise' => 0,
                'horas_soma' => 0.0, 'horas_amostras' => 0, 'bairros' => [], 'zonas' => [], 'cnaes' => [], 'motivos' => [],
            ];
            $celulas[$chave]['total']++;
            $celulas[$chave]['deferidas'] += $ponto->outcome === $deferida ? 1 : 0;
            $celulas[$chave]['indeferidas'] += $ponto->outcome === $indeferida ? 1 : 0;
            $celulas[$chave]['em_analise'] += in_array($ponto->status, self::STATUS_ABERTOS, true) ? 1 : 0;

            if ($ponto->horas !== null) {
                $celulas[$chave]['horas_soma'] += (float) $ponto->horas;
                $celulas[$chave]['horas_amostras']++;
            }

            foreach (['bairros' => $ponto->bairro, 'zonas' => $ponto->zona] as $campo => $valor) {
                if ($valor !== null && $valor !== '') {
                    $celulas[$chave][$campo][$valor] = ($celulas[$chave][$campo][$valor] ?? 0) + 1;
                }
            }

            if ($ponto->outcome === $indeferida) {
                foreach ($this->fundamentos($ponto->fundamentacao) as $fundamento) {
                    $celulas[$chave]['motivos'][$fundamento] = ($celulas[$chave]['motivos'][$fundamento] ?? 0) + 1;
                }
            }

            $celulaDoProcesso[(int) $ponto->id] = $chave;
        }

        if (! $detalhado) {
            uasort($celulas, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

            return array_map(
                fn (array $celula): array => ['lat' => $celula['lat'], 'lng' => $celula['lng'], 'total' => $celula['total']],
                array_slice(array_values($celulas), 0, $c->limite(5000)),
            );
        }

        foreach (array_chunk(array_keys($celulaDoProcesso), 1000) as $ids) {
            $cnaes = DB::table('viability_request_cnaes as vrc')
                ->join('cnaes as c', 'c.id', '=', 'vrc.cnae_id')
                ->whereIn('vrc.viability_request_id', $ids)
                ->get(['vrc.viability_request_id as id', 'c.code as codigo', 'c.description as descricao']);

            foreach ($cnaes as $cnae) {
                $chave = $celulaDoProcesso[(int) $cnae->id];
                $atual = $celulas[$chave]['cnaes'][$cnae->codigo] ?? ['cnae' => (string) $cnae->codigo, 'descricao' => (string) $cnae->descricao, 'total' => 0];
                $atual['total']++;
                $celulas[$chave]['cnaes'][$cnae->codigo] = $atual;
            }
        }

        uasort($celulas, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        $saturacao = [];

        foreach ($this->saturacao->porBairroCnae($c->filtros, 100000) as $par) {
            $saturacao[$par['bairro'].'|'.$par['cnae']] = $par['situacao'];
        }

        $anteriores = $this->totaisDoPeriodoAnterior($c, $precisao);

        return array_map(function (array $celula) use ($saturacao, $anteriores): array {
            arsort($celula['bairros']);
            arsort($celula['zonas']);
            arsort($celula['motivos']);
            $bairro = array_key_first($celula['bairros']);
            $cnaes = array_values($celula['cnaes']);
            usort($cnaes, fn (array $a, array $b): int => [$b['total'], $a['cnae']] <=> [$a['total'], $b['cnae']]);
            $cnaes = array_map(fn (array $cnae): array => [
                ...$cnae,
                'saturacao' => $bairro !== null ? ($saturacao[$bairro.'|'.$cnae['cnae']] ?? null) : null,
            ], array_slice($cnaes, 0, 3));
            $anterior = $anteriores === null ? null : ($anteriores[$celula['lat'].'|'.$celula['lng']] ?? 0);

            return [
                'lat' => $celula['lat'],
                'lng' => $celula['lng'],
                'total' => $celula['total'],
                'deferidas' => $celula['deferidas'],
                'indeferidas' => $celula['indeferidas'],
                'em_analise' => $celula['em_analise'],
                'taxa_indeferimento' => BiEstatistica::percentual($celula['indeferidas'], $celula['deferidas'] + $celula['indeferidas']),
                'bairro' => $bairro,
                'zona' => array_key_first($celula['zonas']),
                'cnaes_principais' => $cnaes,
                'alerta_saturacao' => collect($cnaes)->contains(fn (array $cnae): bool => in_array($cnae['saturacao'], ['saturando', 'saturado'], true)),
                'motivos_indeferimento' => array_map(
                    fn (string $fundamento, int $total): array => ['fundamento' => $fundamento, 'indeferidas' => $total],
                    array_slice(array_keys($celula['motivos']), 0, 3),
                    array_slice(array_values($celula['motivos']), 0, 3),
                ),
                'tempo_medio_horas_uteis' => $celula['horas_amostras'] > 0 ? round($celula['horas_soma'] / $celula['horas_amostras'], 2) : null,
                'total_periodo_anterior' => $anterior,
                'tendencia' => $anterior === null ? null : match (true) {
                    $anterior === 0 => 'nova',
                    $celula['total'] > $anterior => 'subindo',
                    $celula['total'] < $anterior => 'caindo',
                    default => 'estavel',
                },
            ];
        }, array_slice(array_values($celulas), 0, $c->limite(5000)));
    }

    /**
     * Total por célula no período anterior de mesmo tamanho. Sem `data_de`, não
     * há com o que comparar e a tendência fica nula.
     *
     * @return array<string, int>|null
     */
    private function totaisDoPeriodoAnterior(BiConsulta $c, int $precisao): ?array
    {
        $de = $c->filtros->from();

        if ($de === null) {
            return null;
        }

        $ate = $c->filtros->to() ?? Carbon::now()->endOfDay();
        $dias = (int) $de->copy()->startOfDay()->diffInDays($ate->copy()->startOfDay()) + 1;

        $filtros = ReportFilters::fromArray([
            ...$c->filtros->toArray(),
            'data_de' => $de->copy()->subDays($dias)->toDateString(),
            'data_ate' => $de->copy()->subDay()->toDateString(),
        ]);

        $totais = [];

        $pontos = $this->solicitacoes($filtros)
            ->whereNotNull('viability_requests.property_point_lat')
            ->whereNotNull('viability_requests.property_point_lng')
            ->toBase()
            ->select('viability_requests.property_point_lat as lat', 'viability_requests.property_point_lng as lng')
            ->cursor();

        foreach ($pontos as $ponto) {
            $chave = round((float) $ponto->lat, $precisao).'|'.round((float) $ponto->lng, $precisao);
            $totais[$chave] = ($totais[$chave] ?? 0) + 1;
        }

        return $totais;
    }

    /**
     * Fundamentos legais de uma decisão, venham como lista já decodificada ou
     * como o JSON cru da coluna.
     *
     * @return list<string>
     */
    private function fundamentos(mixed $valor): array
    {
        if (is_string($valor)) {
            $valor = json_decode($valor, true);
        }

        return is_array($valor) ? array_values(array_unique(array_filter($valor, 'is_string'))) : [];
    }
}
