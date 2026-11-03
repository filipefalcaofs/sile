<?php

namespace App\Services\Relatorios\Bi;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Services\Relatorios\GeoBairroIndicadorService;
use App\Services\Relatorios\IndicadoresViabilidadeService;
use App\Services\Relatorios\SlaVencimentosService;
use Illuminate\Support\Facades\DB;

/**
 * Visão do secretário: demanda recebida, estoque, desfecho das decisões, tempo e
 * prazo. Reaproveita os serviços dos relatórios para que os números do BI
 * fechem com os das telas de gestão.
 */
class EstrategicoBi
{
    use ConsultasBi;

    public function __construct(
        private readonly IndicadoresViabilidadeService $indicadores,
        private readonly SlaVencimentosService $sla,
        private readonly GeoBairroIndicadorService $geo,
        private readonly EconomicoBi $economico,
    ) {}

    /**
     * @return array<string, int|float|null>
     */
    public function resumoExecutivo(BiConsulta $c): array
    {
        $f = $c->filtros;
        $taxa = $this->indicadores->taxaDeferimento($f);
        $cumprimento = $this->sla->cumprimento($f);

        return [
            'protocoladas' => $this->solicitacoes($f)->count(),
            'em_analise' => $this->estoque($f)->count(),
            'deferidas' => $taxa['deferidas'],
            'indeferidas' => $this->indicadores->taxaIndeferimento($f)['indeferidas'],
            'decididas' => $taxa['total'],
            'taxa_deferimento' => $taxa['taxa'],
            'tempo_medio_analise_horas_uteis' => BiEstatistica::resumo($this->temposViabilidade($f))['media'],
            'processos_fora_do_prazo' => $this->sla->resumo($f)['vencidos'],
            'decididas_fora_do_prazo' => $cumprimento['com_prazo'] - $cumprimento['dentro_sla'],
        ];
    }

    /**
     * Solicitações protocoladas por período.
     *
     * @return array{total: int, serie: list<array<string, mixed>>}
     */
    public function protocoladas(BiConsulta $c): array
    {
        $linhas = $this->solicitacoes($c->filtros)
            ->groupBy('dia')
            ->toBase()
            ->get([
                DB::raw('date(viability_requests.protocoled_at) as dia'),
                DB::raw('count(*) as total'),
            ]);

        $serie = BiEstatistica::agrupar($linhas, $c->agrupamento, [], ['total']);

        return ['total' => array_sum(array_column($serie, 'total')), 'serie' => $serie];
    }

    /**
     * Solicitações ainda em aberto, por situação.
     *
     * @return array{total: int, por_situacao: list<array{situacao: string, rotulo: string, total: int}>}
     */
    public function emAnalise(BiConsulta $c): array
    {
        $porSituacao = $this->estoque($c->filtros)
            ->groupBy('viability_requests.status')
            ->orderByDesc('total')
            ->toBase()
            ->get(['viability_requests.status as situacao', DB::raw('count(*) as total')])
            ->map(fn ($linha): array => [
                'situacao' => (string) $linha->situacao,
                'rotulo' => ViabilityRequestStatus::tryFrom((string) $linha->situacao)?->label() ?? (string) $linha->situacao,
                'total' => (int) $linha->total,
            ])
            ->all();

        return ['total' => array_sum(array_column($porSituacao, 'total')), 'por_situacao' => $porSituacao];
    }

    /**
     * Decisões por período e desfecho.
     *
     * @return array{total: int, deferidas: int, indeferidas: int, taxa_deferimento: float|null, serie: list<array<string, mixed>>}
     */
    public function decisoesPorPeriodo(BiConsulta $c): array
    {
        $linhas = $this->decisoes($c->filtros)
            ->groupBy('dia', 'viability_decisions.outcome')
            ->toBase()
            ->get([
                DB::raw('date(viability_decisions.decided_at) as dia'),
                'viability_decisions.outcome as desfecho',
                DB::raw('count(*) as total'),
            ]);

        $deferida = DecisionOutcome::Deferida->value;
        $indeferida = DecisionOutcome::Indeferida->value;

        $serie = BiEstatistica::agrupar($linhas->map(fn ($linha): object => (object) [
            'dia' => $linha->dia,
            'deferidas' => $linha->desfecho === $deferida ? $linha->total : 0,
            'indeferidas' => $linha->desfecho === $indeferida ? $linha->total : 0,
            'total' => $linha->total,
        ]), $c->agrupamento, [], ['deferidas', 'indeferidas', 'total']);

        $serie = array_map(fn (array $linha): array => [
            ...$linha,
            'taxa_deferimento' => BiEstatistica::percentual($linha['deferidas'], $linha['total']),
        ], $serie);

        $deferidas = array_sum(array_column($serie, 'deferidas'));
        $total = array_sum(array_column($serie, 'total'));

        return [
            'total' => $total,
            'deferidas' => $deferidas,
            'indeferidas' => array_sum(array_column($serie, 'indeferidas')),
            'taxa_deferimento' => BiEstatistica::percentual($deferidas, $total),
            'serie' => $serie,
        ];
    }

    /**
     * @return array{deferidas: int, indeferidas: int, total: int, taxa_deferimento: float|null, taxa_indeferimento: float|null}
     */
    public function taxaDeferimento(BiConsulta $c): array
    {
        $deferimento = $this->indicadores->taxaDeferimento($c->filtros);
        $indeferimento = $this->indicadores->taxaIndeferimento($c->filtros);

        return [
            'deferidas' => $deferimento['deferidas'],
            'indeferidas' => $indeferimento['indeferidas'],
            'total' => $deferimento['total'],
            'taxa_deferimento' => $deferimento['taxa'],
            'taxa_indeferimento' => $indeferimento['taxa'],
        ];
    }

    /**
     * Tempo de viabilidade (protocolo até a decisão, em horas úteis).
     *
     * @return array<string, mixed>
     */
    public function tempoMedioAnalise(BiConsulta $c): array
    {
        return ['unidade' => 'horas_uteis', ...BiEstatistica::resumo($this->temposViabilidade($c->filtros))];
    }

    /**
     * Prazo: o que está vencido hoje e o que foi decidido depois do prazo.
     *
     * @return array<string, mixed>
     */
    public function foraDoPrazo(BiConsulta $c): array
    {
        $cumprimento = $this->sla->cumprimento($c->filtros);

        return [
            'em_andamento' => $this->sla->resumo($c->filtros),
            'por_etapa' => $this->sla->atrasadosPorEtapa($c->filtros),
            'decididas' => [
                'com_prazo' => $cumprimento['com_prazo'],
                'dentro_do_prazo' => $cumprimento['dentro_sla'],
                'fora_do_prazo' => $cumprimento['com_prazo'] - $cumprimento['dentro_sla'],
            ],
        ];
    }

    /**
     * @return array{itens: list<array<string, mixed>>}
     */
    public function porBairro(BiConsulta $c): array
    {
        return ['itens' => $this->geo->porBairro($c->filtros, $c->limite(100))['itens']];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cnaes(BiConsulta $c): array
    {
        return $this->economico->ranking($c);
    }

    /**
     * @return list<array{nivel: string, fonte: string, total: int}>
     */
    public function porRisco(BiConsulta $c): array
    {
        return $this->indicadores->porRisco($c->filtros);
    }
}
