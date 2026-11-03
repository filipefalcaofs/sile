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
            $this->celulas($c),
        );
    }

    /**
     * Deferimentos e indeferimentos por célula de grade.
     *
     * @return list<array{lat: float, lng: float, total: int, deferidas: int, indeferidas: int, taxa_indeferimento: float|null}>
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
     * @return list<array{lat: float, lng: float, total: int, deferidas: int, indeferidas: int, taxa_indeferimento: float|null}>
     */
    private function celulas(BiConsulta $c): array
    {
        $precisao = min(5, max(2, $c->precisao));
        $deferida = DecisionOutcome::Deferida->value;
        $indeferida = DecisionOutcome::Indeferida->value;
        $celulas = [];

        $pontos = $this->solicitacoes($c->filtros)
            ->whereNotNull('viability_requests.property_point_lat')
            ->whereNotNull('viability_requests.property_point_lng')
            ->leftJoin('viability_decisions as vd', 'vd.viability_request_id', '=', 'viability_requests.id')
            ->toBase()
            ->select('viability_requests.property_point_lat as lat', 'viability_requests.property_point_lng as lng', 'vd.outcome as outcome')
            ->cursor();

        foreach ($pontos as $ponto) {
            $lat = round((float) $ponto->lat, $precisao);
            $lng = round((float) $ponto->lng, $precisao);
            $chave = $lat.'|'.$lng;

            $celulas[$chave] ??= ['lat' => $lat, 'lng' => $lng, 'total' => 0, 'deferidas' => 0, 'indeferidas' => 0];
            $celulas[$chave]['total']++;
            $celulas[$chave]['deferidas'] += $ponto->outcome === $deferida ? 1 : 0;
            $celulas[$chave]['indeferidas'] += $ponto->outcome === $indeferida ? 1 : 0;
        }

        uasort($celulas, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return array_map(
            fn (array $celula): array => [
                ...$celula,
                'taxa_indeferimento' => BiEstatistica::percentual($celula['indeferidas'], $celula['deferidas'] + $celula['indeferidas']),
            ],
            array_slice(array_values($celulas), 0, $c->limite(5000)),
        );
    }
}
