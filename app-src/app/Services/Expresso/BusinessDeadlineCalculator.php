<?php

namespace App\Services\Expresso;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Seam de prazo e duração de negócio (HU-134/HU-129).
 *
 * PRAZO (dueAt/isOverdue) — INALTERADO: conta em horas-CALENDÁRIO (addHours) a
 * partir do parâmetro administrável. É a base do SLA da fila (HU-144) e do BAP
 * (HU-134) — Fases 9/10. Tornar o PRAZO em dias úteis depende de confirmação
 * SEDUR do critério legal e fica pendente; até lá a semântica do dueAt não muda
 * (anti-regressão).
 *
 * DURAÇÃO em tempo útil (businessDurationBetween) — NOVO (HU-129): mede a
 * duração DECORRIDA entre dois instantes descontando fins de semana e feriados
 * ATIVOS. É a causa-raiz da distorção do legado (19 dias reportados vs 42h
 * reais) e NÃO é o dueAt forward — é uma operação separada, usada pelo relatório
 * de tempo por etapa. Os feriados vêm do HolidayProvider; sem provider/calendário
 * oficial, só fins de semana são descontados (degradação honesta, jamais feriado
 * inventado).
 */
class BusinessDeadlineCalculator
{
    /**
     * O provider de feriados é OPCIONAL: resolvido pelo container em produção
     * (binding HolidayProvider→DatabaseHolidayProvider) e ausente (null) quando
     * o calculator é instanciado direto (ex.: prazo SLA/BAP, que só usa dueAt).
     * Null ⇒ nenhum feriado descontado (só fins de semana) — honesto.
     */
    public function __construct(
        private readonly ?HolidayProvider $holidays = null,
    ) {}

    /**
     * Vencimento do prazo a partir de um instante e um número de horas.
     * Não muta o argumento de origem.
     */
    public function dueAt(DateTimeInterface $from, int $hours): Carbon
    {
        return Carbon::instance($from)->addHours($hours);
    }

    /**
     * Vencimento a partir de $from somando $businessHours horas ÚTEIS —
     * fins de semana e feriados ativos contribuem 0 (um dia útil = 24h de
     * relógio). É o dueAt FORWARD em tempo útil, usado pelo prazo do convite
     * (48h úteis, relatório SEDUR 2026-07-09). Espelha o desconto de
     * businessDurationBetween, mas projeta para frente. dueAt() (calendário)
     * segue intacto para SLA/BAP (anti-regressão). Não muta a origem.
     */
    public function businessDueAt(DateTimeInterface $from, int $businessHours): Carbon
    {
        $cursor = Carbon::instance($from)->copy();
        $remaining = max(0, $businessHours) * 60; // minutos úteis a consumir

        while ($remaining > 0) {
            if ($cursor->isWeekend() || $this->isHoliday($cursor)) {
                $cursor = $cursor->copy()->startOfDay()->addDay();

                continue;
            }

            $nextDayStart = $cursor->copy()->startOfDay()->addDay();
            $minutesToday = (int) $cursor->diffInMinutes($nextDayStart, absolute: true);

            if ($minutesToday >= $remaining) {
                return $cursor->copy()->addMinutes($remaining);
            }

            $remaining -= $minutesToday;
            $cursor = $nextDayStart;
        }

        return $cursor;
    }

    /**
     * Verdadeiro quando o vencimento já passou em relação a $now (default: agora).
     */
    public function isOverdue(DateTimeInterface $dueAt, ?DateTimeInterface $now = null): bool
    {
        $reference = $now !== null ? Carbon::instance($now) : Carbon::now();

        return Carbon::instance($dueAt)->lessThan($reference);
    }

    /**
     * Duração em MINUTOS úteis decorridos de $from a $to (HU-129): percorre dia a
     * dia somando os minutos de cada dia ÚTIL dentro do intervalo (parcial nas
     * pontas); fim de semana ou feriado ativo contribui 0. Intervalo nulo ou
     * invertido ($to <= $from) ⇒ 0. Sem calendário oficial, só fins de semana
     * caem — nenhum feriado é inventado.
     */
    public function businessDurationBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        $start = Carbon::instance($from);
        $end = Carbon::instance($to);

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        $minutes = 0;
        $cursor = $start->copy();

        while ($cursor->lessThan($end)) {
            $nextDayStart = $cursor->copy()->startOfDay()->addDay();
            $segmentEnd = $end->lessThan($nextDayStart) ? $end : $nextDayStart;

            if (! $cursor->isWeekend() && ! $this->isHoliday($cursor)) {
                $minutes += (int) $cursor->diffInMinutes($segmentEnd, absolute: true);
            }

            $cursor = $nextDayStart;
        }

        return $minutes;
    }

    /**
     * Consulta o provider de feriados quando presente; ausente ⇒ não há feriado
     * (degradação honesta).
     */
    private function isHoliday(DateTimeInterface $date): bool
    {
        return $this->holidays?->isHoliday($date) ?? false;
    }
}
