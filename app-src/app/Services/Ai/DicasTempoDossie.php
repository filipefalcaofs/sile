<?php

namespace App\Services\Ai;

use App\Enums\DecisionOutcome;
use App\Models\ViabilityDecision;
use App\Services\Benchmark\BenchmarkRankingService;
use App\Services\Relatorios\DistanciaBenchmarkService;
use App\Services\Relatorios\GargalosService;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\TempoViabilidadeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Dossiê AGREGADO que alimenta as dicas de IA. Reúne, de uma vez, o que o
 * sistema sabe sobre o tempo de viabilidade no período: tempo e cauda, onde o
 * tempo é gasto, indeferimentos e a referência nacional. Só entra número
 * agregado — nenhum protocolo, nome ou documento sai daqui, porque o dossiê
 * vai a um provedor externo de IA.
 */
class DicasTempoDossie
{
    private const TOP_CAPITAIS = 5;

    private const TOP_CNAES = 5;

    public function __construct(
        private readonly TempoViabilidadeService $tempo,
        private readonly GargalosService $gargalos,
        private readonly DistanciaBenchmarkService $distancia,
        private readonly BenchmarkRankingService $ranking,
    ) {}

    /**
     * Null quando não há processo decidido no período: sem fato, sem dica.
     *
     * @return array<string, mixed>|null
     */
    public function montar(ReportFilters $f): ?array
    {
        $todas = $this->tempo->porPeriodo($f);

        if ($todas['n'] === 0) {
            return null;
        }

        return [
            'periodo' => [
                'de' => $f->from()?->toDateString(),
                'ate' => $f->to()?->toDateString(),
            ],
            'tempo' => [
                'todas' => $todas,
                'deferidas' => $this->tempo->porPeriodoDeferidas($f),
                'por_categoria' => $this->tempo->porCategoria($f),
            ],
            'gargalos' => $this->gargalos->porPeriodo($f),
            'indeferimentos' => $this->indeferimentos($f),
            'benchmark_nacional' => $this->benchmark(),
        ];
    }

    /**
     * Indeferidos pesam no tempo e não entram no benchmark da REDESIM: o
     * dossiê mostra quantos são, quanto demoram e em que atividades.
     *
     * @return array{quantidade: int, percentual_das_decisoes: ?float, media_horas_uteis: ?float, por_resultado: array<string, int>, cnaes_nao_permitidos: list<array{cnae: string, quantidade: int}>}
     */
    private function indeferimentos(ReportFilters $f): array
    {
        $decididas = $this->decisoes($f)->count();

        $indeferidas = $this->decisoes($f)->where('viability_decisions.outcome', DecisionOutcome::Indeferida);

        $quantidade = (clone $indeferidas)->count();

        $media = (clone $indeferidas)
            ->join('viability_requests as vr', 'vr.id', '=', 'viability_decisions.viability_request_id')
            ->whereNotNull('vr.viabilidade_horas_uteis')
            ->avg('vr.viabilidade_horas_uteis');

        $porResultado = (clone $indeferidas)
            ->selectRaw('consolidated_result, count(*) as total')
            ->groupBy('consolidated_result')
            ->pluck('total', 'consolidated_result')
            ->map(static fn ($total): int => (int) $total)
            ->all();

        $cnaes = [];

        foreach ((clone $indeferidas)->select('per_cnae')->cursor() as $decisao) {
            foreach ($decisao->per_cnae ?? [] as $item) {
                if (! is_array($item) || ($item['tendencia'] ?? null) !== 'nao_permitido') {
                    continue;
                }

                $cnae = (string) ($item['cnae_formatado'] ?? $item['cnae'] ?? '');

                if ($cnae !== '') {
                    $cnaes[$cnae] = ($cnaes[$cnae] ?? 0) + 1;
                }
            }
        }

        arsort($cnaes);

        return [
            'quantidade' => $quantidade,
            'percentual_das_decisoes' => $decididas > 0 ? round($quantidade / $decididas * 100, 2) : null,
            'media_horas_uteis' => $media === null ? null : round((float) $media, 2),
            'por_resultado' => $porResultado,
            'cnaes_nao_permitidos' => collect($cnaes)
                ->take(self::TOP_CNAES)
                ->map(static fn (int $quantidade, string $cnae): array => ['cnae' => $cnae, 'quantidade' => $quantidade])
                ->values()
                ->all(),
        ];
    }

    /**
     * Onde Salvador está frente ao país e às capitais mais rápidas, mais o mês
     * corrente contra o mês anterior da REDESIM. Sem competência nacional
     * completa, devolve só a lacuna — nunca um ranking parcial.
     *
     * @return array<string, mixed>
     */
    private function benchmark(): array
    {
        $competencia = $this->ranking->ultimaCompetenciaCompleta();
        $evolucao = $this->distancia->evolucaoMensal(Carbon::now());

        $resultado = [
            'competencia_de_referencia' => $competencia,
            'salvador_contra_redesim' => [
                'mes_anterior_redesim' => $evolucao['anterior'],
                'mes_corrente_viabiliza' => $evolucao['atual'],
                'diferenca_horas' => $evolucao['diferenca_horas'],
                'diferenca_percentual' => $evolucao['diferenca_percentual'],
            ],
        ];

        if ($competencia === null) {
            return $resultado + ['posicao' => null, 'capitais_mais_rapidas' => []];
        }

        $capitais = collect($this->ranking->rankingCapitais($competencia['ano'], $competencia['mes']))
            ->filter(static fn (array $linha): bool => $linha['posicao'] !== null);

        return $resultado + [
            'posicao' => $this->ranking->posicaoNacional($competencia['ano'], $competencia['mes']),
            'capitais_mais_rapidas' => $capitais
                ->take(self::TOP_CAPITAIS)
                ->map(static fn (array $linha): array => [
                    'capital' => $linha['municipio'].'/'.$linha['uf'],
                    'media_horas_uteis' => $linha['media'],
                    'posicao' => $linha['posicao'],
                ])
                ->values()
                ->all(),
            'posicao_de_salvador_entre_capitais' => $capitais
                ->first(static fn (array $linha): bool => $linha['e_salvador'])['posicao'] ?? null,
        ];
    }

    /**
     * @return Builder<ViabilityDecision>
     */
    private function decisoes(ReportFilters $f): Builder
    {
        $de = $f->from();
        $ate = $f->to();

        return ViabilityDecision::query()
            ->when($de !== null, fn (Builder $q) => $q->where('viability_decisions.decided_at', '>=', $de))
            ->when($ate !== null, fn (Builder $q) => $q->where('viability_decisions.decided_at', '<=', $ate));
    }
}
