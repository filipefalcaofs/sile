<?php

namespace Tests\Feature\Relatorios;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\ProcessoQueryService;
use App\Services\Relatorios\Export\Sources\PendenciasReportSource;
use App\Services\Relatorios\IndicadoresViabilidadeService;
use App\Services\Relatorios\PainelOperacaoService;
use App\Services\Relatorios\ReportFilters;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Os recortes por data seguem o dia de Salvador: um protocolo às 23:30 do dia
 * 31 (02:30 UTC do dia seguinte) é do dia 31, do mês e do ano em que ocorreu.
 */
class RecorteDiaDeNegocioTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $seq = 0;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function protocoladaEm(string $local): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => 'VIA-2026-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT),
            'protocoled_at' => Carbon::parse($local, 'America/Bahia')->utc(),
        ]);
    }

    private function volume(string $de, string $ate): int
    {
        $filtros = ReportFilters::fromArray(['data_de' => $de, 'data_ate' => $ate]);

        return array_sum(array_column(app(IndicadoresViabilidadeService::class)->porPeriodo($filtros), 'total'));
    }

    public function test_virada_de_mes_conta_no_mes_local(): void
    {
        $this->protocoladaEm('2026-03-31 23:30:00');
        $this->protocoladaEm('2026-04-01 00:10:00');

        $this->assertSame(1, $this->volume('2026-03-01', '2026-03-31'));
        $this->assertSame(1, $this->volume('2026-04-01', '2026-04-30'));
        $this->assertSame(2, $this->volume('2026-03-31', '2026-04-01'));
    }

    public function test_virada_de_ano_conta_no_ano_local(): void
    {
        $this->protocoladaEm('2026-12-31 22:00:00');
        $this->protocoladaEm('2027-01-01 03:00:00');

        $this->assertSame(1, $this->volume('2026-01-01', '2026-12-31'));
        $this->assertSame(1, $this->volume('2027-01-01', '2027-01-31'));
    }

    public function test_serie_diaria_agrupa_pelo_dia_local(): void
    {
        $this->protocoladaEm('2026-03-31 22:00:00');
        $this->protocoladaEm('2026-03-31 23:30:00');

        $serie = app(IndicadoresViabilidadeService::class)->porPeriodo(ReportFilters::fromArray([
            'data_de' => '2026-03-31',
            'data_ate' => '2026-03-31',
        ]));

        $this->assertSame([['dia' => '2026-03-31', 'total' => 2]], $serie);
    }

    public function test_serie_de_saida_agrupa_decisoes_pelo_dia_local(): void
    {
        $processo = $this->protocoladaEm('2026-03-01 10:00:00');

        ViabilityDecision::factory()->create([
            'viability_request_id' => $processo->id,
            'outcome' => DecisionOutcome::Deferida,
            'tvl_product_number' => null,
            'decided_at' => Carbon::parse('2026-03-31 23:00:00', 'America/Bahia')->utc(),
        ]);

        $serie = app(IndicadoresViabilidadeService::class)->serieFluxo(ReportFilters::fromArray([
            'data_de' => '2026-03-31',
            'data_ate' => '2026-03-31',
        ]));

        $this->assertSame('2026-03-31', $serie[0]['dia']);
        $this->assertSame(1, $serie[0]['saida']);
    }

    public function test_consulta_de_processos_recorta_pelo_dia_local(): void
    {
        $this->protocoladaEm('2026-03-31 23:30:00');

        $consulta = app(ProcessoQueryService::class);

        $this->assertSame(1, $consulta->filtered(['data_de' => '2026-03-31', 'data_ate' => '2026-03-31'])->count());
        $this->assertSame(0, $consulta->filtered(['data_de' => '2026-04-01', 'data_ate' => '2026-04-01'])->count());
    }

    public function test_painel_a_noite_usa_o_dia_local_como_fim_da_janela(): void
    {
        // 15/06 às 23:30 em Salvador = 16/06 02:30 UTC.
        Carbon::setTestNow(Carbon::parse('2026-06-16 02:30:00', 'UTC'));
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->protocoladaEm('2026-06-15 22:00:00');

        $painel = app(PainelOperacaoService::class)->montar(7, false);

        $this->assertSame('2026-06-15', $painel['data_ate']);
        $this->assertSame('2026-06-08', $painel['data_de']);
        $this->assertSame(1, $painel['protocolos']);
    }

    public function test_exportacao_mostra_a_hora_local(): void
    {
        $processo = $this->protocoladaEm('2026-03-10 09:00:00');
        $analista = User::factory()->create();

        AnalysisPendency::factory()->create([
            'viability_request_id' => $processo->id,
            'requested_by_user_id' => $analista->id,
            'status' => AnalysisPendencyStatus::Aberta,
            'created_at' => Carbon::parse('2026-03-10 22:15:00', 'America/Bahia')->utc(),
            'due_at' => Carbon::parse('2026-03-12 22:15:00', 'America/Bahia')->utc(),
        ]);

        $definicao = app(PendenciasReportSource::class)->definition(ReportFilters::fromArray([]));
        $linha = ($definicao->mapRow)($definicao->builder()->first());

        $this->assertContains('10/03/2026 22:15', $linha);
        $this->assertContains('12/03/2026 22:15', $linha);
    }
}
