<?php

namespace App\Services\Relatorios;

use App\Services\Benchmark\BenchmarkRankingService;
use Illuminate\Support\Carbon;

/**
 * Payload da Sala de Controle.
 *
 * Composição pura: nenhuma regra de agregação nasce aqui. Cada bloco é
 * montado em isolamento, de modo que a indisponibilidade de um — tipicamente
 * o benchmark, que depende de ingestão externa — não derrube os demais. O
 * gestor continua vendo o que existe, com o motivo do que falta.
 */
class SalaDeControleService
{
    public function __construct(
        private readonly TempoViabilidadeService $tempoViabilidade,
        private readonly GargalosService $gargalos,
        private readonly DistanciaBenchmarkService $distancia,
        private readonly BenchmarkRankingService $ranking,
        private readonly ClassificacaoFluxoService $classificacao,
    ) {}

    /**
     * @return array{tempo: array<string, mixed>, operacao: array<string, mixed>, distancia: array<string, mixed>, evolucao: list<array<string, mixed>>, gargalos: list<array<string, mixed>>}
     */
    public function montar(ReportFilters $f): array
    {
        $tempo = $this->tempoViabilidade->porPeriodo($f);
        $operacao = $this->operacao($f);

        return [
            'tempo' => $tempo,
            'operacao' => $operacao,
            'operacao_anterior' => $this->operacaoAnterior($f, $operacao['taxa_automacao']),
            'distancia' => $this->distancia->calcular($f, $tempo),
            'evolucao' => $this->ranking->serieSalvador(),
            'gargalos' => $this->gargalos->porPeriodo($f),
        ];
    }

    /**
     * Automação no período anterior de mesma duração, terminando na véspera
     * do recorte. Sem data inicial e final não há período equivalente.
     *
     * @return array{data_de: string, data_ate: string, automatica: int, total: int, taxa_automacao: ?float, diferenca_pontos: ?float}|null
     */
    private function operacaoAnterior(ReportFilters $f, ?float $atual): ?array
    {
        $de = $f->dataDe();
        $ate = $f->dataAte();

        if ($de === null || $ate === null || $de > $ate) {
            return null;
        }

        $dias = (int) Carbon::parse($de)->diffInDays(Carbon::parse($ate)) + 1;
        $fimAnterior = Carbon::parse($de)->subDay();
        $anterior = ReportFilters::fromArray([
            'data_de' => $fimAnterior->copy()->subDays($dias - 1)->toDateString(),
            'data_ate' => $fimAnterior->toDateString(),
        ]);

        $contagem = $this->classificacao->contagem($anterior);

        return [
            'data_de' => (string) $anterior->dataDe(),
            'data_ate' => (string) $anterior->dataAte(),
            'automatica' => $contagem[ClassificacaoFluxoService::AUTOMATICA],
            'total' => $contagem['total'],
            'taxa_automacao' => $contagem['taxa_automacao'],
            'diferenca_pontos' => $atual === null || $contagem['taxa_automacao'] === null
                ? null
                : round($atual - $contagem['taxa_automacao'], 2),
        ];
    }

    /**
     * Processos decididos no período pela forma de conclusão, com a taxa de
     * automação (concluídos pelo motor ÷ decididos × 100) e, à parte, a
     * produtividade semi-expressa: decisões geradas pelo motor e análises
     * humanas sobre elas. Período sem decisão devolve taxa nula, não zero:
     * ausência de medida não é desempenho zero.
     *
     * @return array{automatica: int, semi_expressa: int, humana: int, revisada: int, sem_analise_concluida: int, total: int, taxa_automacao: ?float, decisoes_do_motor: int, analises_humanas: int}
     */
    private function operacao(ReportFilters $f): array
    {
        $semiExpressa = $this->classificacao->produtividadeSemiExpressa($f);

        return [
            ...$this->classificacao->contagem($f),
            'decisoes_do_motor' => $semiExpressa['decisoes_do_motor'],
            'analises_humanas' => $semiExpressa['analises_humanas'],
        ];
    }
}
