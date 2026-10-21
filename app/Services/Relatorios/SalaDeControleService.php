<?php

namespace App\Services\Relatorios;

use App\Services\Benchmark\BenchmarkRankingService;

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

        return [
            'tempo' => $tempo,
            'operacao' => $this->operacao($f),
            'distancia' => $this->distancia->calcular($f, $tempo),
            'evolucao' => $this->ranking->serieSalvador(),
            'gargalos' => $this->gargalos->porPeriodo($f),
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
