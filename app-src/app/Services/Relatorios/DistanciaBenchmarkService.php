<?php

namespace App\Services\Relatorios;

use App\Models\ViabilityDecision;
use App\Services\Benchmark\BenchmarkRankingService;
use Illuminate\Support\Carbon;

/**
 * Distância entre o desempenho atual do VIABILIZA e a referência federal.
 *
 * É o indicador que o gestor lê primeiro, e também o mais fácil de ler
 * errado: os dois lados medem a mesma grandeza, na régua que a REDESIM
 * publica (horas corridas da análise de viabilidade, com fins de semana e
 * feriados), mas vêm de relógios diferentes e de períodos diferentes. Um é telemetria
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
    public const RESSALVA = 'O tempo do VIABILIZA é a medição contínua dos processos deferidos no período selecionado, os que receberam a Viabilidade, que é o universo da REDESIM, onde só entram solicitações deferidas. '
        .'A referência é a apuração da REDESIM para Salvador na competência indicada. '
        .'As duas usam a régua que a REDESIM publica: horas corridas do início da análise à decisão da viabilidade, contando fins de semana e feriados. '
        .'São medições distintas: uma é telemetria operacional, a outra é benchmark periódico.';

    /**
     * Texto que acompanha a série mensal do gráfico "Evolução de Salvador".
     */
    public const RESSALVA_SERIE = 'As duas linhas usam a régua da REDESIM: horas corridas do início da análise à decisão da viabilidade, '
        .'contando fins de semana e feriados, só com processos deferidos. A REDESIM descreve a medida como horas úteis, '
        .'mas os dados que ela publica contam fins de semana e feriados. Na REDESIM o mês é o do registro na Junta; '
        .'no VIABILIZA, o da decisão da viabilidade.';

    /**
     * Quantos meses antes do início da operação formam a linha "Antes do VIABILIZA".
     */
    private const MESES_ANTES_DA_OPERACAO = 12;

    public function __construct(
        private readonly TempoViabilidadeService $tempoViabilidade,
        private readonly BenchmarkRankingService $ranking,
    ) {}

    /**
     * @return array{interno: ?float, referencia: ?float, competencia: array{ano: int, mes: int}|null, diferenca_horas: ?float, diferenca_percentual: ?float, motivo: ?string, ressalva: string}
     */
    public function calcular(ReportFilters $f): array
    {
        $interno = $this->tempoViabilidade->horasCorridasDeferidas($f)['media'];
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
     * @return array{serie: array<string, mixed>, anterior: array{ano: int, mes: int, media: ?float, p50: ?float, p90: ?float, n: int}, atual: array{ano: int, mes: int, media: ?float, p50: ?float, p90: ?float, n: int}, redesim_atual: array{ano: int, mes: int, media: ?float, p50: ?float, p90: ?float, n: int}, diferenca_horas: ?float, diferenca_percentual: ?float, diferenca_mesmo_mes_horas: ?float, diferenca_mesmo_mes_percentual: ?float, ressalva: string}
     */
    public function evolucaoMensal(Carbon $hoje): array
    {
        $mesAnterior = $hoje->copy()->startOfMonth()->subMonthNoOverflow();

        $anterior = $this->pontoRedesim($mesAnterior->year, $mesAnterior->month);
        $redesimAtual = $this->pontoRedesim($hoje->year, $hoje->month);

        $interno = $this->tempoViabilidade->horasCorridasDeferidas(ReportFilters::fromArray([
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
            'serie' => $this->serieMensal($hoje),
            'ressalva' => self::RESSALVA,
        ];
    }

    /**
     * Série mensal do gráfico: um ponto por mês, do mês em que o VIABILIZA
     * começou a operar até o mês corrente, limitada aos últimos 12 meses. Cada
     * ponto traz a REDESIM de Salvador (nula até a importação da competência) e
     * o VIABILIZA do mês (deferidos decididos no mês; no corrente, até hoje),
     * os dois na régua da REDESIM: horas corridas.
     *
     * @return array{inicio_operacao: array{ano: int, mes: int}|null, antes_da_operacao: array{media: float, n: int, meses: int, de: array{ano: int, mes: int}, ate: array{ano: int, mes: int}}|null, ressalva: string, pontos: list<array{ano: int, mes: int, parcial: bool, redesim: array{media: ?float, p50: ?float, p90: ?float, n: int}, viabiliza: array{media: ?float, p50: ?float, p90: ?float, n: int}}>}
     */
    private function serieMensal(Carbon $hoje): array
    {
        $corrente = $hoje->copy()->startOfMonth();
        $inicio = $this->inicioDaOperacao();
        $janela = $corrente->copy()->subMonthsNoOverflow(11);
        $cursor = $inicio === null ? $corrente->copy() : $inicio->copy()->max($janela);

        $pontos = [];

        while ($cursor->lessThanOrEqualTo($corrente)) {
            $ehCorrente = $cursor->equalTo($corrente);
            $redesim = $this->pontoRedesim($cursor->year, $cursor->month);
            $interno = $this->tempoViabilidade->horasCorridasDeferidas(ReportFilters::fromArray([
                'data_de' => $cursor->toDateString(),
                'data_ate' => ($ehCorrente ? $hoje : $cursor->copy()->endOfMonth())->toDateString(),
            ]));

            $pontos[] = [
                'ano' => $cursor->year,
                'mes' => $cursor->month,
                'parcial' => $ehCorrente,
                'redesim' => [
                    'media' => $redesim['media'], 'p50' => $redesim['p50'], 'p90' => $redesim['p90'], 'n' => $redesim['n'],
                ],
                'viabiliza' => [
                    'media' => $interno['media'], 'p50' => $interno['p50'], 'p90' => $interno['p90'], 'n' => $interno['n'],
                ],
            ];

            $cursor = $cursor->copy()->addMonthNoOverflow();
        }

        return [
            'inicio_operacao' => $inicio === null ? null : ['ano' => $inicio->year, 'mes' => $inicio->month],
            'antes_da_operacao' => $inicio === null ? null : $this->antesDaOperacao($inicio),
            'pontos' => $pontos,
            'ressalva' => self::RESSALVA_SERIE,
        ];
    }

    /**
     * Linha de base: a REDESIM de Salvador nos 12 meses anteriores ao início
     * da operação, quando a viabilidade ainda era feita no sistema anterior. A
     * média de cada mês pesa pelo número de solicitações dele, para dar a média
     * do período inteiro e não a média das médias.
     *
     * @return array{media: float, n: int, meses: int, de: array{ano: int, mes: int}, ate: array{ano: int, mes: int}}|null
     */
    private function antesDaOperacao(Carbon $inicio): ?array
    {
        $soma = 0.0;
        $solicitacoes = 0;
        $meses = [];
        $cursor = $inicio->copy()->subMonthsNoOverflow(self::MESES_ANTES_DA_OPERACAO);

        while ($cursor->lessThan($inicio)) {
            $metrica = $this->ranking->metricaDeSalvador($cursor->year, $cursor->month);

            if ($metrica?->media !== null && (int) $metrica->n > 0) {
                $soma += (float) $metrica->media * (int) $metrica->n;
                $solicitacoes += (int) $metrica->n;
                $meses[] = ['ano' => $cursor->year, 'mes' => $cursor->month];
            }

            $cursor = $cursor->copy()->addMonthNoOverflow();
        }

        if ($solicitacoes === 0) {
            return null;
        }

        return [
            'media' => round($soma / $solicitacoes, 2),
            'n' => $solicitacoes,
            'meses' => count($meses),
            'de' => $meses[0],
            'ate' => $meses[count($meses) - 1],
        ];
    }

    /**
     * Mês em que o VIABILIZA começou a operar: o configurado em
     * `viabiliza.operacao.inicio` ou, sem ele, o mês da primeira decisão
     * deferida. Sem nenhum dos dois, não há início conhecido.
     */
    private function inicioDaOperacao(): ?Carbon
    {
        $configurado = config('viabiliza.operacao.inicio');

        if (is_string($configurado) && preg_match('/^\d{4}-\d{2}$/', $configurado) === 1) {
            return Carbon::createFromFormat('Y-m', $configurado)->startOfMonth();
        }

        $primeira = ViabilityDecision::query()->whereNotNull('tvl_product_number')->min('decided_at');

        return $primeira === null ? null : Carbon::parse($primeira)->startOfMonth();
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
