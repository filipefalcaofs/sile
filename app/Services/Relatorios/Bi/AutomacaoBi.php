<?php

namespace App\Services\Relatorios\Bi;

use App\Enums\DecisionOutcome;
use App\Models\ExpressoQueda;
use App\Services\Relatorios\ExpressoQuedaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Automação do licenciamento: quanto das decisões sai pelo fluxo expresso, em
 * quanto tempo e por que o resto cai para a análise técnica.
 */
class AutomacaoBi
{
    use ConsultasBi;

    private const FLUXO_EXPRESSO = 'expresso';

    private const FLUXO_TECNICO = 'analise_tecnica';

    public function __construct(private readonly ExpressoQuedaService $quedas) {}

    /**
     * @return array{total: int, automaticas: int, analise_tecnica: int, outras: int, taxa_automacao: float|null}
     */
    public function taxaAutomacao(BiConsulta $c): array
    {
        $porFluxo = $this->decisoes($c->filtros)
            ->groupBy('viability_decisions.flow')
            ->toBase()
            ->get(['viability_decisions.flow as fluxo', DB::raw('count(*) as total')])
            ->pluck('total', 'fluxo');

        $automaticas = (int) ($porFluxo[self::FLUXO_EXPRESSO] ?? 0);
        $tecnicas = (int) ($porFluxo[self::FLUXO_TECNICO] ?? 0);
        $total = (int) $porFluxo->sum();

        return [
            'total' => $total,
            'automaticas' => $automaticas,
            'analise_tecnica' => $tecnicas,
            'outras' => $total - $automaticas - $tecnicas,
            'taxa_automacao' => BiEstatistica::percentual($automaticas, $total),
        ];
    }

    /**
     * Decisões concluídas sem análise humana, por período.
     *
     * @return array{total: int, serie: list<array<string, mixed>>}
     */
    public function decisoesAutomaticas(BiConsulta $c): array
    {
        return $this->serieDoFluxo($c, self::FLUXO_EXPRESSO);
    }

    /**
     * Decisões que exigiram análise técnica, por período.
     *
     * @return array{total: int, serie: list<array<string, mixed>>}
     */
    public function decisoesTecnicas(BiConsulta $c): array
    {
        return $this->serieDoFluxo($c, self::FLUXO_TECNICO);
    }

    /**
     * Tempo de viabilidade (horas úteis) do fluxo expresso × análise técnica.
     *
     * @return array<string, mixed>
     */
    public function tempoPorModalidade(BiConsulta $c): array
    {
        return [
            'unidade' => 'horas_uteis',
            'expresso' => BiEstatistica::resumo($this->temposViabilidade($c->filtros, self::FLUXO_EXPRESSO)),
            'analise_tecnica' => BiEstatistica::resumo($this->temposViabilidade($c->filtros, self::FLUXO_TECNICO)),
        ];
    }

    /**
     * Processos que entraram no expresso e não saíram decididos por ele.
     *
     * @return array{elegiveis: int, concluidos_pelo_expresso: int, nao_concluidos: int, taxa_nao_conclusao: float|null, encaminhados_a_analise: int}
     */
    public function naoConcluidosPeloExpresso(BiConsulta $c): array
    {
        $resposta = $this->quedas->taxaRespostaExpressa($c->filtros);
        $naoConcluidos = max(0, $resposta['elegiveis'] - $resposta['respondidas']);

        $encaminhados = ExpressoQueda::query()
            ->when($c->filtros->from(), fn (Builder $q, $de): Builder => $q->where('created_at', '>=', $de))
            ->when($c->filtros->to(), fn (Builder $q, $ate): Builder => $q->where('created_at', '<=', $ate))
            ->distinct()
            ->count('viability_request_id');

        return [
            'elegiveis' => $resposta['elegiveis'],
            'concluidos_pelo_expresso' => $resposta['respondidas'],
            'nao_concluidos' => $naoConcluidos,
            'taxa_nao_conclusao' => BiEstatistica::percentual($naoConcluidos, $resposta['elegiveis']),
            'encaminhados_a_analise' => $encaminhados,
        ];
    }

    /**
     * Condições que derrubam o processo do expresso para a análise técnica.
     *
     * @return list<array{posicao: int, tipo_gatilho: string|null, rotulo: string, total: int, participacao: float|null}>
     */
    public function motivosQueda(BiConsulta $c): array
    {
        $motivos = $this->quedas->rankingMotivos($c->filtros);
        $total = array_sum(array_column($motivos, 'total'));

        return array_map(fn (array $motivo, int $i): array => [
            'posicao' => $i + 1,
            ...$motivo,
            'participacao' => BiEstatistica::percentual($motivo['total'], $total),
        ], $motivos, array_keys($motivos));
    }

    /**
     * Automação ao longo do tempo (por mês, salvo outro agrupamento).
     *
     * @return list<array<string, mixed>>
     */
    public function evolucao(BiConsulta $c): array
    {
        $linhas = $this->decisoes($c->filtros)
            ->groupBy('dia', 'viability_decisions.flow')
            ->toBase()
            ->get([
                DB::raw('date(viability_decisions.decided_at) as dia'),
                'viability_decisions.flow as fluxo',
                DB::raw('count(*) as total'),
            ]);

        $serie = BiEstatistica::agrupar($linhas->map(fn ($linha): object => (object) [
            'dia' => $linha->dia,
            'total' => $linha->total,
            'automaticas' => $linha->fluxo === self::FLUXO_EXPRESSO ? $linha->total : 0,
            'analise_tecnica' => $linha->fluxo === self::FLUXO_TECNICO ? $linha->total : 0,
        ]), $c->agrupamento, [], ['total', 'automaticas', 'analise_tecnica']);

        return array_map(fn (array $linha): array => [
            ...$linha,
            'taxa_automacao' => BiEstatistica::percentual($linha['automaticas'], $linha['total']),
        ], $serie);
    }

    /**
     * @return array{total: int, serie: list<array<string, mixed>>}
     */
    private function serieDoFluxo(BiConsulta $c, string $fluxo): array
    {
        $deferida = DecisionOutcome::Deferida->value;
        $indeferida = DecisionOutcome::Indeferida->value;

        $linhas = $this->decisoes($c->filtros)
            ->where('viability_decisions.flow', $fluxo)
            ->groupBy('dia', 'viability_decisions.outcome')
            ->toBase()
            ->get([
                DB::raw('date(viability_decisions.decided_at) as dia'),
                'viability_decisions.outcome as desfecho',
                DB::raw('count(*) as total'),
            ]);

        $serie = BiEstatistica::agrupar($linhas->map(fn ($linha): object => (object) [
            'dia' => $linha->dia,
            'total' => $linha->total,
            'deferidas' => $linha->desfecho === $deferida ? $linha->total : 0,
            'indeferidas' => $linha->desfecho === $indeferida ? $linha->total : 0,
        ]), $c->agrupamento, [], ['total', 'deferidas', 'indeferidas']);

        return ['total' => array_sum(array_column($serie, 'total')), 'serie' => $serie];
    }
}
