<?php

namespace App\Services\Expresso;

use DateTimeInterface;

/**
 * Contrato da fonte de feriados (HU-137), consumido pelo BusinessDeadlineCalculator
 * para descontar dias não-úteis na duração em tempo útil (HU-129). Atrás de
 * interface para centralizar o seam: hoje a fonte é o banco (DatabaseHolidayProvider,
 * cacheado); a regra/origem pode trocar sem tocar o calculator nem seus call sites.
 *
 * Degradação honesta (entrega-funcional): sem feriado municipal oficial
 * cadastrado, hasOfficialCalendar() é false e o relatório exibe a ressalva —
 * nenhum feriado é inventado.
 */
interface HolidayProvider
{
    /**
     * Verdadeiro quando a data é um feriado ATIVO (recorrente anual por mês/dia
     * ou data específica cadastrada).
     */
    public function isHoliday(DateTimeInterface $date): bool;

    /**
     * Verdadeiro só quando há ao menos um feriado NÃO-recorrente (municipal
     * específico) cadastrado — base da ressalva honesta do relatório. Apenas os
     * feriados nacionais fixos (recorrentes) NÃO caracterizam o calendário
     * oficial municipal (pendência SEDUR).
     */
    public function hasOfficialCalendar(): bool;
}
