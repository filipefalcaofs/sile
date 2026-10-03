<?php

namespace Tests\Feature\Relatorios;

use App\Enums\AnalysisCategory;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\TempoViabilidadeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Distribuição do tempo de viabilidade por período. O recorte é pela data da
 * DECISÃO: o indicador mede processos concluídos na janela, não protocolados.
 */
class TempoViabilidadeServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

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
        $this->decidido(1.0, '2026-03-02 10:00:00', AnalysisCategory::Expresso);
        $this->decidido(3.0, '2026-03-03 10:00:00', AnalysisCategory::Expresso);
        $this->decidido(40.0, '2026-03-04 10:00:00', AnalysisCategory::SemiExpresso);

        $resultado = $this->service()->porCategoria($this->filtros('2026-03-01', '2026-03-31'));

        $this->assertSame(2, $resultado[AnalysisCategory::Expresso->value]['n']);
        $this->assertEqualsWithDelta(2.0, $resultado[AnalysisCategory::Expresso->value]['media'], 0.01);
        $this->assertSame(1, $resultado[AnalysisCategory::SemiExpresso->value]['n']);
        $this->assertEqualsWithDelta(40.0, $resultado[AnalysisCategory::SemiExpresso->value]['media'], 0.01);
    }

    public function test_sem_periodo_informado_considera_todos_os_processos_decididos(): void
    {
        $this->decidido(2.0, '2025-01-02 10:00:00');
        $this->decidido(4.0, '2026-03-03 10:00:00');

        $resultado = $this->service()->porPeriodo(ReportFilters::fromArray([]));

        $this->assertSame(2, $resultado['n']);
    }
}
