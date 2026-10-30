<?php

namespace App\Services\Relatorios;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Expresso\BusinessDeadlineCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * Onde o tempo é gasto — "o que está nos segurando".
 *
 * As faixas são DERIVADAS dos intervalos entre transições de status: nenhum
 * analista classifica motivo de demora. Por construção elas somam o tempo
 * total do processo, o que torna o número auditável contra o indicador de
 * tempo.
 *
 * Duas ausências deliberadas: vistoria ocorre DENTRO da análise, então não é
 * faixa paralela; e queda do expresso é evento instantâneo, não consumo de
 * tempo — está no relatório de quedas (HU-135), não aqui.
 */
class GargalosService
{
    /**
     * Status canônico do processo para a faixa de gargalo exibida ao gestor.
     * Status fora deste mapa cai em "outras etapas" — nada é descartado em
     * silêncio, senão as faixas deixariam de somar o total.
     */
    private const FAIXAS = [
        'protocolada' => ['fila', 'Fila'],
        'em_analise' => ['analise_manual', 'Análise manual'],
        'em_pendencia' => ['pendencia_documental', 'Pendência documental'],
        'aguardando_bap' => ['aguardando_bap', 'Aguardando BAP'],
    ];

    public function __construct(
        private readonly BusinessDeadlineCalculator $prazos,
    ) {}

    /**
     * @return list<array{faixa: string, rotulo: string, horas: float, percentual: float}>
     */
    public function porPeriodo(ReportFilters $f): array
    {
        $minutosPorFaixa = [];

        foreach ($this->processos($f) as $processo) {
            foreach ($this->minutosDoProcesso($processo) as $faixa => $minutos) {
                $minutosPorFaixa[$faixa] = ($minutosPorFaixa[$faixa] ?? 0) + $minutos;
            }
        }

        return $this->formatar($minutosPorFaixa);
    }

    /**
     * Minutos úteis por faixa num processo: cada intervalo entre transições
     * consecutivas é rotulado pelo status que o abriu, e o último vai da
     * última transição até a decisão.
     *
     * @return array<string, int>
     */
    private function minutosDoProcesso(ViabilityRequest $processo): array
    {
        $decididoEm = $processo->decision?->decided_at;

        if ($decididoEm === null) {
            return [];
        }

        $transicoes = $processo->transitions
            ->sortBy('created_at')
            ->values();

        $minutos = [];

        foreach ($transicoes as $indice => $transicao) {
            $inicio = $transicao->created_at;
            $fim = $transicoes->get($indice + 1)?->created_at ?? $decididoEm;

            if ($inicio === null || $fim->lessThanOrEqualTo($inicio)) {
                continue;
            }

            [$faixa] = $this->faixaDe($transicao->to_status);

            $minutos[$faixa] = ($minutos[$faixa] ?? 0)
                + $this->prazos->businessDurationBetween($inicio, $fim);
        }

        return $minutos;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function faixaDe(ViabilityRequestStatus|string|null $status): array
    {
        $chave = $status instanceof ViabilityRequestStatus ? $status->value : (string) $status;

        return self::FAIXAS[$chave] ?? ['outras_etapas', 'Outras etapas'];
    }

    /**
     * @param  array<string, int>  $minutosPorFaixa
     * @return list<array{faixa: string, rotulo: string, horas: float, percentual: float}>
     */
    private function formatar(array $minutosPorFaixa): array
    {
        $total = array_sum($minutosPorFaixa);

        if ($total === 0) {
            return [];
        }

        $rotulos = array_column(self::FAIXAS, 1, 0) + ['outras_etapas' => 'Outras etapas'];

        $faixas = [];

        foreach ($minutosPorFaixa as $faixa => $minutos) {
            $faixas[] = [
                'faixa' => $faixa,
                'rotulo' => $rotulos[$faixa] ?? 'Outras etapas',
                'horas' => round($minutos / 60, 2),
                'percentual' => round($minutos / $total * 100, 2),
            ];
        }

        usort($faixas, static fn (array $a, array $b): int => $b['horas'] <=> $a['horas']);

        return $faixas;
    }

    /**
     * Lê em lotes: o período inteiro, com transições, não cabe na memória do pod.
     *
     * @return LazyCollection<int, ViabilityRequest>
     */
    private function processos(ReportFilters $f): LazyCollection
    {
        $de = $f->from();
        $ate = $f->to();

        return ViabilityRequest::query()
            ->with(['transitions', 'decision'])
            ->whereHas('decision', function (Builder $query) use ($de, $ate): void {
                $query
                    ->when($de !== null, fn (Builder $q) => $q->where('decided_at', '>=', $de))
                    ->when($ate !== null, fn (Builder $q) => $q->where('decided_at', '<=', $ate));
            })
            ->lazyById(200);
    }
}
