<?php

namespace App\Services\Relatorios;

use App\Enums\AnalysisCategory;
use App\Models\ViabilityRequest;
use App\Support\Metricas\Percentis;
use Illuminate\Database\Eloquent\Builder;

/**
 * Distribuição do tempo de viabilidade por período, na régua federal.
 *
 * Média sozinha esconde a cauda: é a diferença entre "o tempo médio é de
 * segundos" e "95% saem em segundos, mas existe um resíduo de horas". Por isso
 * o indicador entrega a distribuição inteira.
 *
 * O recorte é pela data da DECISÃO — o indicador mede o que foi concluído na
 * janela. Processo sem tempo apurado fica de fora, e período sem processos
 * devolve a estrutura nula: ausência de medida nunca vira zero.
 */
class TempoViabilidadeService
{
    /**
     * @return array{n: int, media: ?float, p50: ?float, p75: ?float, p90: ?float, p95: ?float, p99: ?float, maximo: ?float}
     */
    public function porPeriodo(ReportFilters $f): array
    {
        return Percentis::de($this->horas($f))->toArray();
    }

    /**
     * Mesma distribuição, só dos processos deferidos (com número de TVL). É o
     * universo do export da REDESIM, em que toda linha traz data de deferimento:
     * viabilidade indeferida não aparece lá, então compará-la com a referência
     * federal inflaria o nosso lado.
     *
     * @return array{n: int, media: ?float, p50: ?float, p75: ?float, p90: ?float, p95: ?float, p99: ?float, maximo: ?float}
     */
    public function porPeriodoDeferidas(ReportFilters $f): array
    {
        return Percentis::de($this->horas($f, somenteDeferidas: true))->toArray();
    }

    /**
     * Distribuição por categoria de análise, que é onde o gargalo aparece: o
     * expresso resolve em segundos, o que vai à análise humana leva horas.
     *
     * @return array<string, array{n: int, media: ?float, p50: ?float, p75: ?float, p90: ?float, p95: ?float, p99: ?float, maximo: ?float}>
     */
    public function porCategoria(ReportFilters $f): array
    {
        $resultado = [];

        foreach (AnalysisCategory::cases() as $categoria) {
            $resultado[$categoria->value] = Percentis::de(
                $this->horas($f, $categoria),
            )->toArray();
        }

        return $resultado;
    }

    /**
     * @return list<float>
     */
    private function horas(ReportFilters $f, ?AnalysisCategory $categoria = null, bool $somenteDeferidas = false): array
    {
        return $this->baseQuery($f, $somenteDeferidas)
            ->when($categoria !== null, fn (Builder $q) => $q->where('analysis_category', $categoria))
            ->pluck('viabilidade_horas_uteis')
            ->map(static fn ($valor): float => (float) $valor)
            ->all();
    }

    /**
     * @return Builder<ViabilityRequest>
     */
    private function baseQuery(ReportFilters $f, bool $somenteDeferidas = false): Builder
    {
        $de = $f->from();
        $ate = $f->to();

        return ViabilityRequest::query()
            ->whereNotNull('viabilidade_horas_uteis')
            ->whereHas('decision', function ($query) use ($de, $ate, $somenteDeferidas): void {
                $query
                    ->when($somenteDeferidas, fn ($q) => $q->whereNotNull('tvl_product_number'))
                    ->when($de !== null, fn ($q) => $q->where('decided_at', '>=', $de))
                    ->when($ate !== null, fn ($q) => $q->where('decided_at', '<=', $ate));
            });
    }
}
