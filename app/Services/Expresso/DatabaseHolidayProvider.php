<?php

namespace App\Services\Expresso;

use App\Models\Holiday;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Fonte de feriados respaldada na tabela holidays (HU-137), com cache de leitura
 * (TTL técnico de config). Carrega os feriados ATIVOS uma vez e responde
 * isHoliday por mês/dia (recorrentes anuais) e por data exata (específicos).
 *
 * hasOfficialCalendar é verdadeiro só quando há ao menos um feriado
 * NÃO-recorrente cadastrado: os nacionais fixos (recorrentes do seeder) são o
 * piso honesto, mas NÃO caracterizam o calendário municipal oficial — que é
 * pendência SEDUR. Sem ele, o relatório exibe a ressalva e o cálculo desconta
 * só fins de semana + os nacionais, jamais um feriado inventado.
 */
class DatabaseHolidayProvider implements HolidayProvider
{
    /**
     * Chave única do cache do calendário carregado. A invalidação se dá pelo TTL
     * (efeito sem deploy, limitado ao cache — padrão HU-014); o CRUD futuro de
     * feriados deve esquecê-la na gravação para efeito imediato.
     */
    private const CACHE_KEY = 'sile.expresso.holidays';

    public function isHoliday(DateTimeInterface $date): bool
    {
        $calendar = $this->calendar();
        $carbon = Carbon::instance($date);

        return in_array($carbon->format('m-d'), $calendar['recurring'], true)
            || in_array($carbon->format('Y-m-d'), $calendar['specific'], true);
    }

    public function hasOfficialCalendar(): bool
    {
        return $this->calendar()['specific'] !== [];
    }

    /**
     * Calendário carregado e cacheado: feriados recorrentes (mês/dia) e
     * específicos (data exata), apenas os ATIVOS.
     *
     * @return array{recurring: list<string>, specific: list<string>}
     */
    private function calendar(): array
    {
        $ttl = (int) config('sile.relatorios.cache_ttl_segundos', 300);

        return Cache::remember(self::CACHE_KEY, $ttl, function (): array {
            $recurring = [];
            $specific = [];

            foreach (Holiday::query()->active()->get() as $holiday) {
                $date = Carbon::instance($holiday->date);

                if ($holiday->recurring_annually) {
                    $recurring[] = $date->format('m-d');
                } else {
                    $specific[] = $date->format('Y-m-d');
                }
            }

            return ['recurring' => $recurring, 'specific' => $specific];
        });
    }
}
