<?php

namespace Tests\Feature\Relatorios;

use App\Enums\AnalysisCategory;
use App\Enums\ConclusionOutcome;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\ClassificacaoFluxoService;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\TempoViabilidadeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\MontaFluxosDeConclusao;
use Tests\TestCase;

/**
 * Distribuição do tempo de viabilidade por período. O recorte é pela data da
 * DECISÃO: o indicador mede processos concluídos na janela, não protocolados.
 */
class TempoViabilidadeServiceTest extends TestCase
{
    use LazilyRefreshDatabase;
    use MontaFluxosDeConclusao;

    private function service(): TempoViabilidadeService
    {
        return app(TempoViabilidadeService::class);
    }

    /**
     * Cria um processo decidido com o tempo de viabilidade já apurado.
     */
    private function decidido(float $horas, string $decididoEm, ?AnalysisCategory $categoria = null): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => fake()->unique()->numerify('VIA-2026-######'),
            'protocoled_at' => Carbon::parse($decididoEm)->subDay(),
            'analysis_category' => $categoria,
        ]);

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'tvl_product_number' => fake()->unique()->numerify('TVL-2026-######'),
            'decided_at' => Carbon::parse($decididoEm),
        ]);

        $request->forceFill(['viabilidade_horas_uteis' => $horas])->save();

        return $request;
    }

    private function filtros(string $de, string $ate): ReportFilters
    {
        return ReportFilters::fromArray(['data_de' => $de, 'data_ate' => $ate]);
    }

    public function test_apura_a_distribuicao_dos_processos_decididos_no_periodo(): void
    {
        $this->decidido(2.0, '2026-03-02 10:00:00');
        $this->decidido(4.0, '2026-03-03 10:00:00');
        $this->decidido(6.0, '2026-03-04 10:00:00');

        $resultado = $this->service()->porPeriodo($this->filtros('2026-03-01', '2026-03-31'));

        $this->assertSame(3, $resultado['n']);
        $this->assertEqualsWithDelta(4.0, $resultado['media'], 0.01);
        $this->assertEqualsWithDelta(4.0, $resultado['p50'], 0.01);
        $this->assertEqualsWithDelta(6.0, $resultado['maximo'], 0.01);
    }

    public function test_ignora_processos_decididos_fora_do_periodo(): void
    {
        $this->decidido(2.0, '2026-03-02 10:00:00');
        $this->decidido(99.0, '2026-04-02 10:00:00');

        $resultado = $this->service()->porPeriodo($this->filtros('2026-03-01', '2026-03-31'));

        $this->assertSame(1, $resultado['n']);
        $this->assertEqualsWithDelta(2.0, $resultado['media'], 0.01);
    }

    public function test_ignora_processos_sem_tempo_apurado(): void
    {
        $semTempo = $this->decidido(5.0, '2026-03-02 10:00:00');
        $semTempo->forceFill(['viabilidade_horas_uteis' => null])->save();

        $this->decidido(3.0, '2026-03-03 10:00:00');

        $resultado = $this->service()->porPeriodo($this->filtros('2026-03-01', '2026-03-31'));

        $this->assertSame(1, $resultado['n']);
        $this->assertEqualsWithDelta(3.0, $resultado['media'], 0.01);
    }

    public function test_periodo_sem_processos_devolve_estrutura_nula(): void
    {
        $resultado = $this->service()->porPeriodo($this->filtros('2026-03-01', '2026-03-31'));

        $this->assertSame(0, $resultado['n']);
        $this->assertNull($resultado['media']);
        $this->assertNull($resultado['p90']);
    }

    public function test_separa_a_distribuicao_por_categoria_de_analise(): void
    {
        Carbon::setTestNow('2026-03-10 12:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->cenarioZonaPermitida();

        $this->concluidaPeloMotor();
        $this->concluidaPeloMotor();
        $semi = $this->encaminhadaComDecisaoDoMotor(['protocoled_at' => Carbon::parse('2026-03-09 12:00:00')]);
        $this->concluirAnalise($semi, User::factory()->analistaServidor()->create(), ConclusionOutcome::Deferido);

        $resultado = $this->service()->porCategoria($this->filtros('2026-03-01', '2026-03-31'));

        $this->assertSame([ClassificacaoFluxoService::AUTOMATICA, ClassificacaoFluxoService::SEMI_EXPRESSA, ClassificacaoFluxoService::HUMANA], array_keys($resultado));
        $this->assertSame(2, $resultado[ClassificacaoFluxoService::AUTOMATICA]['n']);
        $this->assertSame(1, $resultado[ClassificacaoFluxoService::SEMI_EXPRESSA]['n']);
        $this->assertSame(0, $resultado[ClassificacaoFluxoService::HUMANA]['n']);
        $this->assertGreaterThan($resultado[ClassificacaoFluxoService::AUTOMATICA]['media'], $resultado[ClassificacaoFluxoService::SEMI_EXPRESSA]['media']);
    }

    public function test_sem_periodo_informado_considera_todos_os_processos_decididos(): void
    {
        $this->decidido(2.0, '2025-01-02 10:00:00');
        $this->decidido(4.0, '2026-03-03 10:00:00');

        $resultado = $this->service()->porPeriodo(ReportFilters::fromArray([]));

        $this->assertSame(2, $resultado['n']);
    }
}
