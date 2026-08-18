<?php

namespace Tests\Unit\Expresso;

use App\Models\Holiday;
use App\Services\Expresso\BusinessDeadlineCalculator;
use App\Services\Expresso\DatabaseHolidayProvider;
use App\Services\Expresso\HolidayProvider;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Duração em tempo útil entre dois instantes (HU-129) — a operação NOVA que o
 * dueAt forward (horas-calendário, SLA/BAP das Fases 9/10) NÃO faz. Desconta
 * integralmente fins de semana e feriados ATIVOS, sem NUNCA inventar feriado:
 * sem calendário oficial, só os fins de semana caem (degradação honesta).
 */
class BusinessDeadlineCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_duracao_util_desconta_fim_de_semana(): void
    {
        $calculator = new BusinessDeadlineCalculator($this->fakeProvider(hasOfficial: true));

        $from = Carbon::parse('2026-01-09 14:00:00'); // sexta
        $to = Carbon::parse('2026-01-12 14:00:00');   // segunda

        // sex 14h→24h (600) + sáb 0 + dom 0 + seg 0h→14h (840) = 1440 min (1 dia útil).
        $this->assertSame(1440, $calculator->businessDurationBetween($from, $to));
    }

    public function test_duracao_util_desconta_feriado_cadastrado(): void
    {
        // Terça (2026-01-06) marcada como feriado pelo provider.
        $calculator = new BusinessDeadlineCalculator(
            $this->fakeProvider(hasOfficial: true, holidayDates: ['2026-01-06']),
        );

        $from = Carbon::parse('2026-01-05 00:00:00'); // segunda
        $to = Carbon::parse('2026-01-07 00:00:00');   // quarta

        // seg (1440) + ter feriado (0) = 1440 min (1 dia útil).
        $this->assertSame(1440, $calculator->businessDurationBetween($from, $to));
    }

    public function test_sem_calendario_oficial_desconta_so_fim_de_semana(): void
    {
        // Provider sem calendário oficial e sem feriado cadastrado.
        $provider = $this->fakeProvider(hasOfficial: false);
        $calculator = new BusinessDeadlineCalculator($provider);

        // 2 de Julho (feriado real da Bahia) cai numa quinta em 2026 e NÃO está
        // cadastrado: anti-fachada → conta como dia útil normal (1440), nenhum
        // feriado inventado.
        $from = Carbon::parse('2026-07-02 00:00:00');
        $to = Carbon::parse('2026-07-03 00:00:00');

        $this->assertSame(1440, $calculator->businessDurationBetween($from, $to));
        $this->assertFalse($provider->hasOfficialCalendar());
    }

    public function test_intervalo_invertido_ou_nulo_retorna_zero(): void
    {
        $calculator = new BusinessDeadlineCalculator($this->fakeProvider(hasOfficial: true));

        $from = Carbon::parse('2026-01-09 14:00:00');

        // to < from
        $this->assertSame(0, $calculator->businessDurationBetween($from, $from->copy()->subHour()));
        // to == from
        $this->assertSame(0, $calculator->businessDurationBetween($from, $from->copy()));
    }

    public function test_database_holiday_provider_desconta_feriado_real_end_to_end(): void
    {
        // Feriado MUNICIPAL específico (não-recorrente) → hasOfficialCalendar=true.
        Holiday::factory()->create([
            'date' => '2026-03-04', // quarta
            'name' => 'Feriado Municipal de Teste',
            'recurring_annually' => false,
            'active' => true,
        ]);

        $provider = new DatabaseHolidayProvider;
        $calculator = new BusinessDeadlineCalculator($provider);

        $this->assertTrue($provider->isHoliday(Carbon::parse('2026-03-04')));
        $this->assertTrue($provider->hasOfficialCalendar());

        // ter (1440) + qua feriado (0) = 1440 min — desconto real end-to-end.
        $this->assertSame(
            1440,
            $calculator->businessDurationBetween(
                Carbon::parse('2026-03-03 00:00:00'),
                Carbon::parse('2026-03-05 00:00:00'),
            ),
        );
    }

    public function test_database_holiday_provider_recorrente_sem_municipal_degrada_honesto(): void
    {
        // Só feriado nacional recorrente (Natal) → desconta por mês/dia, mas
        // hasOfficialCalendar=false (lista municipal oficial é pendência SEDUR).
        Holiday::factory()->recurring()->create([
            'date' => '2026-12-25',
            'name' => 'Natal',
        ]);

        $provider = new DatabaseHolidayProvider;

        $this->assertTrue($provider->isHoliday(Carbon::parse('2030-12-25')));
        $this->assertFalse($provider->hasOfficialCalendar());
    }

    public function test_inativo_nao_e_descontado(): void
    {
        // Feriado inativo não conta (mantém histórico, fora do cálculo).
        Holiday::factory()->inactive()->create([
            'date' => '2026-03-04', // quarta
            'name' => 'Feriado Revogado',
            'recurring_annually' => false,
        ]);

        $provider = new DatabaseHolidayProvider;

        $this->assertFalse($provider->isHoliday(Carbon::parse('2026-03-04')));
        $this->assertFalse($provider->hasOfficialCalendar());
    }

    /**
     * Stub controlável de HolidayProvider (não toca o banco): isHoliday casa por
     * data exata (Y-m-d) e hasOfficialCalendar é fixo.
     *
     * @param  list<string>  $holidayDates
     */
    private function fakeProvider(bool $hasOfficial, array $holidayDates = []): HolidayProvider
    {
        return new class($hasOfficial, $holidayDates) implements HolidayProvider
        {
            /**
             * @param  list<string>  $holidayDates
             */
            public function __construct(
                private readonly bool $hasOfficial,
                private readonly array $holidayDates,
            ) {}

            public function isHoliday(DateTimeInterface $date): bool
            {
                return in_array(Carbon::instance($date)->format('Y-m-d'), $this->holidayDates, true);
            }

            public function hasOfficialCalendar(): bool
            {
                return $this->hasOfficial;
            }
        };
    }
}
