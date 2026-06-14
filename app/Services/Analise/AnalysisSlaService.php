<?php

namespace App\Services\Analise;

use App\Enums\AnalysisStage;
use App\Services\Expresso\BusinessDeadlineCalculator;
use App\Support\Settings;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * SLA da fila de análise técnica (HU-144). Centraliza o cálculo do prazo-limite
 * absoluto por etapa (analysis_due_at — base da ordenação da fila, coluna
 * indexada de 10-02) e do semáforo + tempo restante.
 *
 * O prazo é MATERIALIZADO por quem transiciona (10-07 grava o dueAtFor no
 * analysis_due_at); a fila (10-14) e o badge (10-16) consomem o statusFor, que
 * calcula a cor ON-THE-FLY a cada leitura — nunca há cor persistida. Reusar o
 * BusinessDeadlineCalculator mantém o seam de HU-137 pronto: quando a contagem
 * passar a pular fins de semana/feriados, a regra muda SÓ no calculator, sem
 * tocar este serviço nem seus call sites.
 *
 * Tudo parametrizável (HU-014) com DEFAULT INLINE: o serviço funciona mesmo sem
 * o seeder 10-01 (banco vazio cai no fallback de config e, por fim, no inline).
 */
class AnalysisSlaService
{
    /**
     * Conversão dias→horas: o prazo é parametrizado em DIAS (decisão de negócio)
     * e o calculator conta em horas-calendário. HU-137 (dias úteis) entra no
     * calculator sem alterar esta conversão.
     */
    private const HORAS_POR_DIA = 24;

    /**
     * Defaults inline do prazo por etapa (alinhados ao catálogo HU-014 do
     * 10-01). Etapa sem default mapeado cai no FALLBACK_DIAS — fallback razoável
     * para uma etapa futura ainda não parametrizada (jamais prazo zero/infinito).
     */
    private const DEFAULT_DIAS = [
        'distribuicao' => 2,
        'analise' => 10,
    ];

    private const FALLBACK_DIAS = 10;

    public function __construct(
        private readonly BusinessDeadlineCalculator $calculator,
    ) {}

    /**
     * Prazo (em dias) da etapa: parâmetro administrável analise.sla.<etapa>_dias
     * com default inline (distribuicao=2, analise=10) — Settings resolve
     * banco → config/sile.php → default do call site.
     */
    public function diasDaEtapa(AnalysisStage $stage): int
    {
        $default = self::DEFAULT_DIAS[$stage->value] ?? self::FALLBACK_DIAS;

        return (int) Settings::get(
            "analise.sla.{$stage->value}_dias",
            config("sile.analise.sla.{$stage->value}_dias", $default),
        );
    }

    /**
     * Prazo-limite absoluto da etapa a partir de uma origem (default: agora),
     * reusando o BusinessDeadlineCalculator (seam HU-137). Não muta $from.
     */
    public function dueAtFor(AnalysisStage $stage, ?DateTimeInterface $from = null): Carbon
    {
        $from ??= Carbon::now();

        return $this->calculator->dueAt($from, $this->diasDaEtapa($stage) * self::HORAS_POR_DIA);
    }
}
