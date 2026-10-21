<?php

namespace App\Services\Relatorios;

use App\Services\Benchmark\BenchmarkRankingService;
use Illuminate\Support\Carbon;

/**
 * Distância entre o desempenho atual do VIABILIZA e a referência federal.
 *
 * É o indicador que o gestor lê primeiro, e também o mais fácil de ler
 * errado: os dois lados medem a mesma grandeza — horas úteis de viabilidade —
 * mas vêm de relógios diferentes e de períodos diferentes. Um é telemetria
 * contínua do sistema; o outro é apuração periódica publicada pela REDESIM.
 *
 * Por isso a ressalva que identifica as duas réguas é devolvida AQUI, junto
 * do número, e não deixada a cargo da tela: assim não há como exibir a
 * diferença sem exibir o que ela significa.
 */
class DistanciaBenchmarkService
{
    /**
     * Texto que acompanha obrigatoriamente o número.
     */
    public const RESSALVA = 'O tempo do VIABILIZA é a medição contínua dos processos deferidos no período selecionado — os que receberam o TVL, que é o universo da REDESIM, onde só entram solicitações deferidas. '
        .'A referência é a apuração da REDESIM para Salvador na competência indicada. '
        .'As duas usam a mesma régua — horas úteis de viabilidade, sem fins de semana, feriados nem o tempo com o cidadão — '
        .'mas são medições distintas: uma é telemetria operacional, a outra é benchmark periódico.';

    public function __construct(
        private readonly TempoViabilidadeService $tempoViabilidade,
        private readonly BenchmarkRankingService $ranking,
    ) {}

    /**
     * @return array{interno: ?float, referencia: ?float, competencia: array{ano: int, mes: int}|null, diferenca_horas: ?float, diferenca_percentual: ?float, motivo: ?string, ressalva: string}
     */
    public function calcular(ReportFilters $f): array
    {
        $interno = $this->tempoViabilidade->porPeriodoDeferidas($f)['media'];
        $competencia = $this->ranking->ultimaCompetenciaCompleta();

        $referencia = $competencia === null
            ? null
            : $this->ranking->metricaDeSalvador($competencia['ano'], $competencia['mes'])?->media;

        if ($interno === null || $referencia === null) {
            return $this->semComparacao($interno, $referencia, $competencia);
        }

        $diferenca = $interno - $referencia;

        return [
            'interno' => $interno,
            'referencia' => $referencia,
            'competencia' => $competencia,
            'diferenca_horas' => round($diferenca, 2),
            // Referência zero não permite variação relativa — devolver null é
            // mais honesto que inventar um percentual infinito.
            'diferenca_percentual' => $referencia > 0 ? round($diferenca / $referencia * 100, 2) : null,
            'motivo' => null,
            'ressalva' => self::RESSALVA,
        ];
    }

    /**
     * Evolução de Salvador em três medidas: a REDESIM do mês anterior, o
     * VIABILIZA do mês corrente (processos decididos de dia 1º até hoje, em
     * tempo real) e a REDESIM do mês corrente, que só existe depois que a
     * origem consolida a competência e o benchmark a importa — normalmente no
     * mês seguinte. Medida sem dado volta com média nula e n zero, nunca com
     * zero medido.
     *
     * A diferença principal é do VIABILIZA contra a REDESIM do mês anterior. A
     * diferença "do mesmo mês" só existe quando as duas medidas do mês corrente
     * estão disponíveis — é a única comparação entre competências iguais.
     *
     * @return array{anterior: array{ano: int, mes: int, media: ?float, p50: ?float, p90: ?float, n: int}, atual: array{ano: int, mes: int, media: ?float, p50: ?float, p90: ?float, n: int}, redesim_atual: array{ano: int, mes: int, media: ?float, p50: ?float, p90: ?float, n: int}, diferenca_horas: ?float, diferenca_percentual: ?float, diferenca_mesmo_mes_horas: ?float, diferenca_mesmo_mes_percentual: ?float, ressalva: string}
     */
    public function evolucaoMensal(Carbon $hoje): array
    {
        $mesAnterior = $hoje->copy()->startOfMonth()->subMonthNoOverflow();

        $anterior = $this->pontoRedesim($mesAnterior->year, $mesAnterior->month);
        $redesimAtual = $this->pontoRedesim($hoje->year, $hoje->month);

        $interno = $this->tempoViabilidade->porPeriodoDeferidas(ReportFilters::fromArray([
            'data_de' => $hoje->copy()->startOfMonth()->toDateString(),
            'data_ate' => $hoje->toDateString(),
        ]));

        $viabiliza = [
            'ano' => $hoje->year,
            'mes' => $hoje->month,
            'media' => $interno['media'],
            'p50' => $interno['p50'],
            'p90' => $interno['p90'],
            'n' => $interno['n'],
        ];

        [$horas, $percentual] = $this->diferenca($viabiliza['media'], $anterior['media']);
        [$horasMesmoMes, $percentualMesmoMes] = $this->diferenca($viabiliza['media'], $redesimAtual['media']);

        return [
            'anterior' => $anterior,
            'atual' => $viabiliza,
            'redesim_atual' => $redesimAtual,
            'diferenca_horas' => $horas,
            'diferenca_percentual' => $percentual,
            'diferenca_mesmo_mes_horas' => $horasMesmoMes,
            'diferenca_mesmo_mes_percentual' => $percentualMesmoMes,
            'ressalva' => self::RESSALVA,
        ];
    }

    /**
     * @return array{ano: int, mes: int, media: ?float, p50: ?float, p90: ?float, n: int}
     */
    private function pontoRedesim(int $ano, int $mes): array
    {
        $metrica = $this->ranking->metricaDeSalvador($ano, $mes);

        return [
            'ano' => $ano,
            'mes' => $mes,
            'media' => $metrica?->media,
            'p50' => $metrica?->p50,
            'p90' => $metrica?->p90,
            'n' => (int) ($metrica?->n ?? 0),
        ];
    }

    /**
     * Diferença em horas e em percentual sobre a referência. Sem as duas
     * medidas, ou com referência zero, o que não puder ser calculado fica nulo.
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function diferenca(?float $interno, ?float $referencia): array
    {
        if ($interno === null || $referencia === null) {
            return [null, null];
        }

        $diferenca = $interno - $referencia;

        return [
            round($diferenca, 2),
            $referencia > 0 ? round($diferenca / $referencia * 100, 2) : null,
        ];
    }

    /**
     * @param  array{ano: int, mes: int}|null  $competencia
     * @return array{interno: ?float, referencia: ?float, competencia: array{ano: int, mes: int}|null, diferenca_horas: null, diferenca_percentual: null, motivo: string, ressalva: string}
     */
    private function semComparacao(?float $interno, ?float $referencia, ?array $competencia): array
    {
        $motivo = match (true) {
            $interno === null && $referencia === null => 'Sem processos decididos no período e sem competência de referência importada.',
            $interno === null => 'Sem processos decididos no período selecionado.',
            default => 'Sem competência de referência com cobertura nacional completa.',
        };

        return [
            'interno' => $interno,
            'referencia' => $referencia,
            'competencia' => $referencia === null ? null : $competencia,
            'diferenca_horas' => null,
            'diferenca_percentual' => null,
            'motivo' => $motivo,
            'ressalva' => self::RESSALVA,
        ];
    }
}
