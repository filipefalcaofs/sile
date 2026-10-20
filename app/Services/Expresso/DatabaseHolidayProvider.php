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
 * NÃO-recorrente cadastrado à mão ou um feriado
 * municipal vindo da FeriadosAPI: os nacionais fixos (recorrentes do seeder) são o
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
    public const CACHE_KEY = 'viabiliza.expresso.holidays';

    /**
     * Calendário já lido nesta instância. O binding é scoped (uma instância por
     * requisição ou job), então um cálculo que pergunta dia a dia lê o cache
     * uma vez só.
     *
     * @var array{recurring: list<string>, specific: list<string>, official: bool}|null
     */
    private ?array $carregado = null;

    /** Geração do calendário lida em {@see $carregado}. */
    private int $geracaoCarregada = -1;

    /** Sobe a cada gravação de feriado no processo; invalida o que foi lido antes. */
    private static int $geracao = 0;

    /**
     * Descarta o calendário cacheado e o já lido pelas instâncias deste
     * processo — chamado quando um feriado é gravado ou removido.
     */
    public static function esquecer(): void
    {
        self::$geracao++;
        Cache::forget(self::CACHE_KEY);
    }

    public function isHoliday(DateTimeInterface $date): bool
    {
        $calendar = $this->calendar();
        $carbon = Carbon::instance($date);

        return in_array($carbon->format('m-d'), $calendar['recurring'], true)
            || in_array($carbon->format('Y-m-d'), $calendar['specific'], true);
    }

    public function hasOfficialCalendar(): bool
    {
        return $this->calendar()['official'];
    }

    /**
     * Calendário carregado e cacheado: feriados recorrentes (mês/dia) e
     * específicos (data exata), apenas os ATIVOS.
     *
     * @return array{recurring: list<string>, specific: list<string>, official: bool}
     */
    private function calendar(): array
    {
        if ($this->carregado !== null && $this->geracaoCarregada === self::$geracao) {
            return $this->carregado;
        }

        $this->geracaoCarregada = self::$geracao;
        $ttl = (int) config('viabiliza.relatorios.cache_ttl_segundos', 300);

        return $this->carregado = Cache::remember(self::CACHE_KEY, $ttl, function (): array {
            $recurring = [];
            $official = false;
            $specific = [];

            foreach (Holiday::query()->active()->get() as $holiday) {
                $date = Carbon::instance($holiday->date);

                if ($holiday->recurring_annually) {
                    $recurring[] = $date->format('m-d');
                } else {

                    // Da API, só o municipal caracteriza o calendário de Salvador;
                    // nacional/estadual sincronizado não tira a ressalva.
                    $official = $official || $holiday->source !== 'api' || $holiday->type === 'municipal';
                    $specific[] = $date->format('Y-m-d');
                }
            }

            return ['recurring' => $recurring, 'specific' => $specific, 'official' => $official];
        });
    }
}
