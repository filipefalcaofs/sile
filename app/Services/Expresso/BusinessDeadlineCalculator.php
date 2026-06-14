<?php

namespace App\Services\Expresso;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Seam de prazo de negócio (HU-134 RN-001). HOJE conta em horas-CALENDÁRIO
 * (addHours) a partir do parâmetro administrável expresso.bap.prazo_horas.
 *
 * Ponto de extensão (HU-137 — feriados/fins de semana): quando a contagem em
 * DIAS ÚTEIS entrar, a regra muda AQUI (dueAt passa a pular fins de semana e
 * feriados oficiais) SEM tocar nenhum call site — o comando/serviço só dependem
 * de dueAt()/isOverdue(). Até lá NÃO há calendário de feriados inventado: a
 * contagem é honesta em horas corridas.
 */
class BusinessDeadlineCalculator
{
    /**
     * Vencimento do prazo a partir de um instante e um número de horas.
     * Não muta o argumento de origem.
     */
    public function dueAt(DateTimeInterface $from, int $hours): Carbon
    {
        return Carbon::instance($from)->addHours($hours);
    }

    /**
     * Verdadeiro quando o vencimento já passou em relação a $now (default: agora).
     */
    public function isOverdue(DateTimeInterface $dueAt, ?DateTimeInterface $now = null): bool
    {
        $reference = $now !== null ? Carbon::instance($now) : Carbon::now();

        return Carbon::instance($dueAt)->lessThan($reference);
    }
}
