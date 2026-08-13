<?php

namespace Tests\Unit\Expresso;

use App\Services\Expresso\BusinessDeadlineCalculator;
use App\Services\Expresso\HolidayProvider;
use Carbon\Carbon;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

class BusinessDueAtTest extends TestCase
{
    public function test_soma_horas_uteis_pulando_fim_de_semana(): void
    {
        // Sexta 14h + 48h úteis (dia útil = 24h de relógio, só pula fds/feriado):
        // 10h restam sexta → 38h → sáb/dom pulados → segunda 24h → 14h restam → terça 0..14h.
        $calc = new BusinessDeadlineCalculator();
        $sexta14 = Carbon::parse('2026-07-10 14:00:00'); // sexta
        $due = $calc->businessDueAt($sexta14, 48);
        $this->assertSame('2026-07-14 14:00:00', $due->format('Y-m-d H:i:s')); // terça
    }

    public function test_pula_feriado(): void
    {
        $feriado = new class implements HolidayProvider
        {
            public function isHoliday(DateTimeInterface $date): bool
            {
                return $date->format('Y-m-d') === '2026-07-13'; // segunda vira feriado
            }

            public function hasOfficialCalendar(): bool
            {
                return true;
            }
        };
        $calc = new BusinessDeadlineCalculator($feriado);
        $sexta14 = Carbon::parse('2026-07-10 14:00:00');
        $due = $calc->businessDueAt($sexta14, 48);
        // segunda(13) é feriado → pula → terça(14) 24h → quarta(15) 14h
        $this->assertSame('2026-07-15 14:00:00', $due->format('Y-m-d H:i:s'));
    }

    public function test_zero_horas_devolve_o_instante_de_origem(): void
    {
        $calc = new BusinessDeadlineCalculator();
        $inicio = Carbon::parse('2026-07-10 14:00:00');
        $this->assertSame(
            $inicio->format('Y-m-d H:i:s'),
            $calc->businessDueAt($inicio, 0)->format('Y-m-d H:i:s'),
        );
    }
}
