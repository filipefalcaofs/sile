<?php

namespace Tests\Unit\Support;

use App\Services\Expresso\BusinessDeadlineCalculator;
use App\Services\Relatorios\ReportFilters;
use App\Support\FusoNegocio;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * O banco grava em UTC e o dia de negócio é o de Salvador (America/Bahia,
 * UTC−3): os limites de um período informado como data viram o intervalo UTC
 * que cobre o dia local inteiro, sem perder nem repetir registro na fronteira.
 */
class FusoNegocioTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_inicio_e_fim_do_dia_local_em_utc(): void
    {
        $this->assertSame('2026-03-01 03:00:00', FusoNegocio::inicioDoDia('2026-03-01')->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', FusoNegocio::inicioDoDia('2026-03-01')->getTimezone()->getName());
        $this->assertSame('2026-04-01 02:59:59.999999', FusoNegocio::fimDoDia('2026-03-31')->format('Y-m-d H:i:s.u'));
    }

    public function test_filtro_de_periodo_cobre_o_dia_local_inteiro(): void
    {
        $filtros = ReportFilters::fromArray(['data_de' => '2026-12-01', 'data_ate' => '2026-12-31']);

        $this->assertSame('2026-12-01 03:00:00', $filtros->from()->format('Y-m-d H:i:s'));
        $this->assertSame('2027-01-01 02:59:59', $filtros->to()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-01', $filtros->dataDe());
        $this->assertSame('2026-12-31', $filtros->dataAte());
    }

    public function test_dias_consecutivos_nao_se_sobrepoem_nem_deixam_buraco(): void
    {
        $fim = FusoNegocio::fimDoDia('2026-03-31');
        $inicio = FusoNegocio::inicioDoDia('2026-04-01');

        $this->assertSame(1, (int) $fim->diffInMicroseconds($inicio));
    }

    public function test_hoje_e_o_dia_local_depois_das_21h(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-16 02:30:00', 'UTC'));

        $this->assertSame('2026-06-15', FusoNegocio::hoje()->toDateString());
        $this->assertSame('2026-06-15 23:30', FusoNegocio::local(Carbon::now())->format('Y-m-d H:i'));
        $this->assertNull(FusoNegocio::local(null));
    }

    public function test_dia_sql_por_driver(): void
    {
        $this->assertSame("date(protocoled_at, '-180 minutes')", FusoNegocio::diaSql('protocoled_at', 'sqlite'));
        $this->assertSame("date((protocoled_at AT TIME ZONE 'UTC') AT TIME ZONE 'America/Bahia')", FusoNegocio::diaSql('protocoled_at', 'pgsql'));
    }

    public function test_tempo_util_desconta_o_fim_de_semana_local(): void
    {
        $calculadora = new BusinessDeadlineCalculator;

        // Sexta 22:00 → segunda 01:00 (horário de Salvador): as 2 h finais da
        // sexta e a 1 h da segunda são úteis; sábado e domingo não.
        $de = Carbon::parse('2026-06-12 22:00:00', 'America/Bahia')->utc();
        $ate = Carbon::parse('2026-06-15 01:00:00', 'America/Bahia')->utc();

        $this->assertSame(180, $calculadora->businessDurationBetween($de, $ate));
    }
}
