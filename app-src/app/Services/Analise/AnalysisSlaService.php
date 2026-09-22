<?php

namespace App\Services\Analise;

use App\Enums\AnalysisStage;
use App\Services\Expresso\BusinessDeadlineCalculator;
use App\Support\Settings;
use Carbon\CarbonInterface;
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

    /**
     * Semáforo do SLA calculado ON-THE-FLY (nunca persistido) + tempo restante
     * legível. Vermelho quando o prazo estourou (isOverdue do calculator);
     * amarelo ao atingir o limiar parametrizável da fração decorrida; verde caso
     * contrário. $now é injetável para teste determinístico.
     *
     * @return array{status: SlaStatus, restante: string}
     */
    public function statusFor(
        DateTimeInterface $dueAt,
        DateTimeInterface $startedAt,
        ?DateTimeInterface $now = null,
    ): array {
        $dueAt = Carbon::instance($dueAt);
        $startedAt = Carbon::instance($startedAt);
        $now = $now !== null ? Carbon::instance($now) : Carbon::now();

        return [
            'status' => $this->semaforo($dueAt, $startedAt, $now),
            // Tempo restante relativo a $now ("em 5 dias" / "há 2 dias" quando
            // estourado), em pt-BR independentemente do locale da app.
            'restante' => $dueAt->locale('pt_BR')->diffForHumans([
                'other' => $now,
                'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW,
                'parts' => 2,
            ]),
        ];
    }

    private function semaforo(Carbon $dueAt, Carbon $startedAt, Carbon $now): SlaStatus
    {
        if ($this->calculator->isOverdue($dueAt, $now)) {
            return SlaStatus::Vermelho;
        }

        return $this->fracaoDecorrida($startedAt, $dueAt, $now) >= $this->limiarAmarelo()
            ? SlaStatus::Amarelo
            : SlaStatus::Verde;
    }

    /**
     * Limiar (0..1) a partir do qual o semáforo fica amarelo, do parâmetro
     * administrável analise.sla.semaforo.amarelo_percentual (default inline 80%).
     */
    private function limiarAmarelo(): float
    {
        $percentual = (int) Settings::get(
            'analise.sla.semaforo.amarelo_percentual',
            config('sile.analise.sla.semaforo.amarelo_percentual', 80),
        );

        return $percentual / 100;
    }

    /**
     * Fração da janela do prazo já decorrida (0..1). Janela degenerada
     * (startedAt >= dueAt) trata como no limite; antes do início, como zero.
     */
    private function fracaoDecorrida(Carbon $startedAt, Carbon $dueAt, Carbon $now): float
    {
        $total = $startedAt->diffInSeconds($dueAt, absolute: true);

        if ($total <= 0.0) {
            return 1.0;
        }

        $decorrido = max(0.0, (float) $startedAt->diffInSeconds($now, absolute: false));

        return $decorrido / $total;
    }
}
