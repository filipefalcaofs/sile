<?php

namespace App\Services\Relatorios;

use App\Services\Benchmark\BenchmarkRankingService;

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
    public const RESSALVA = 'O tempo do VIABILIZA é a medição contínua dos processos decididos no período selecionado. '
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
        $interno = $this->tempoViabilidade->porPeriodo($f)['media'];
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
