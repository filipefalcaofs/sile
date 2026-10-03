<?php

namespace App\Services\Relatorios;

use App\Enums\AnalysisCategory;
use App\Models\ViabilityRequest;
use App\Services\Benchmark\BenchmarkRankingService;
use Illuminate\Database\Eloquent\Builder;

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
    ) {}

    /**
     * @return array{tempo: array<string, mixed>, operacao: array<string, mixed>, distancia: array<string, mixed>, evolucao: list<array<string, mixed>>, gargalos: list<array<string, mixed>>}
     */
    public function montar(ReportFilters $f): array
    {
        return [
            'tempo' => $this->tempoViabilidade->porPeriodo($f),
            'operacao' => $this->operacao($f),
            'distancia' => $this->distancia->calcular($f),
            'evolucao' => $this->ranking->serieSalvador(),
            'gargalos' => $this->gargalos->porPeriodo($f),
        ];
    }

    /**
     * Volume decidido no período e quanto disso saiu sem análise humana.
     * Período sem decisão devolve taxa nula, não zero: ausência de medida não
     * é desempenho zero.
     *
     * @return array{total: int, expresso: int, semi_expresso: int, taxa_automacao: ?float}
     */
    private function operacao(ReportFilters $f): array
    {
        $total = $this->decididos($f)->count();
        $expresso = $this->decididos($f)->where('analysis_category', AnalysisCategory::Expresso)->count();
        $semiExpresso = $this->decididos($f)->where('analysis_category', AnalysisCategory::SemiExpresso)->count();

        return [
            'total' => $total,
            'expresso' => $expresso,
            'semi_expresso' => $semiExpresso,
            'taxa_automacao' => $total > 0 ? round($expresso / $total * 100, 2) : null,
        ];
    }

    /**
     * @return Builder<ViabilityRequest>
     */
    private function decididos(ReportFilters $f): Builder
    {
        $de = $f->from();
        $ate = $f->to();

        return ViabilityRequest::query()
            ->whereHas('decision', function (Builder $query) use ($de, $ate): void {
                $query
                    ->when($de !== null, fn (Builder $q) => $q->where('decided_at', '>=', $de))
                    ->when($ate !== null, fn (Builder $q) => $q->where('decided_at', '<=', $ate));
            });
    }
}
