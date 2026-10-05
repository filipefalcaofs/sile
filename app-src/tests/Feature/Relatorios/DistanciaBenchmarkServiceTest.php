<?php

namespace Tests\Feature\Relatorios;

use App\Models\BenchmarkIngestion;
use App\Models\BenchmarkMunicipalityMetric;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\DistanciaBenchmarkService;
use App\Services\Relatorios\ReportFilters;
use App\Support\Capitais;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Distância entre o que o VIABILIZA mede agora e a referência federal.
 *
 * Os dois lados vêm em horas úteis de viabilidade. A ressalva que identifica
 * as duas réguas é devolvida PELO SERVIÇO, junto do número — a tela não tem
 * como renderizar um sem o outro.
 */
class DistanciaBenchmarkServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): DistanciaBenchmarkService
    {
        return app(DistanciaBenchmarkService::class);
    }

    private function filtros(): ReportFilters
    {
        return ReportFilters::fromArray(['data_de' => '2026-03-01', 'data_ate' => '2026-03-31']);
    }

    private function processoInternoCom(float $horas): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => fake()->unique()->numerify('VIA-2026-######'),
            'protocoled_at' => Carbon::parse('2026-03-02 00:00:00'),
        ]);

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'tvl_product_number' => fake()->unique()->numerify('TVL-2026-######'),
            'decided_at' => Carbon::parse('2026-03-03 00:00:00'),
        ]);

        $request->forceFill(['viabilidade_horas_uteis' => $horas])->save();
    }

    private function referenciaCom(float $media): void
    {
        foreach (Capitais::ufs() as $uf) {
            BenchmarkIngestion::factory()->create(['uf' => $uf, 'ano' => 2026, 'mes' => 8]);
        }

        BenchmarkMunicipalityMetric::factory()->salvador()->create([
            'ano' => 2026, 'mes' => 8, 'media' => $media,
        ]);
    }

    public function test_compara_o_tempo_interno_com_a_referencia_da_competencia(): void
    {
        $this->processoInternoCom(2.0);
        $this->referenciaCom(20.0);

        $distancia = $this->service()->calcular($this->filtros());

        $this->assertEqualsWithDelta(2.0, $distancia['interno'], 0.01);
        $this->assertEqualsWithDelta(20.0, $distancia['referencia'], 0.01);
        $this->assertEqualsWithDelta(-18.0, $distancia['diferenca_horas'], 0.01);
        $this->assertEqualsWithDelta(-90.0, $distancia['diferenca_percentual'], 0.01);
        $this->assertSame(['ano' => 2026, 'mes' => 8], $distancia['competencia']);
    }

    public function test_tempo_interno_maior_que_a_referencia_produz_diferenca_positiva(): void
    {
        $this->processoInternoCom(30.0);
        $this->referenciaCom(20.0);

        $distancia = $this->service()->calcular($this->filtros());

        $this->assertEqualsWithDelta(10.0, $distancia['diferenca_horas'], 0.01);
        $this->assertEqualsWithDelta(50.0, $distancia['diferenca_percentual'], 0.01);
    }

    public function test_sem_competencia_de_referencia_nao_calcula_diferenca(): void
    {
        $this->processoInternoCom(2.0);

        $distancia = $this->service()->calcular($this->filtros());

        $this->assertNull($distancia['referencia']);
        $this->assertNull($distancia['diferenca_horas']);
        $this->assertNull($distancia['diferenca_percentual']);
        $this->assertNull($distancia['competencia']);
        $this->assertNotNull($distancia['motivo']);
    }

    public function test_sem_processo_interno_no_periodo_nao_calcula_diferenca(): void
    {
        $this->referenciaCom(20.0);

        $distancia = $this->service()->calcular($this->filtros());

        $this->assertNull($distancia['interno']);
        $this->assertNull($distancia['diferenca_horas']);
        $this->assertNotNull($distancia['motivo']);
    }

    public function test_referencia_zero_nao_produz_divisao_por_zero(): void
    {
        $this->processoInternoCom(2.0);
        $this->referenciaCom(0.0);

        $distancia = $this->service()->calcular($this->filtros());

        $this->assertEqualsWithDelta(2.0, $distancia['diferenca_horas'], 0.01);
        $this->assertNull($distancia['diferenca_percentual']);
    }

    public function test_devolve_a_ressalva_metodologica_junto_do_numero(): void
    {
        $this->processoInternoCom(2.0);
        $this->referenciaCom(20.0);

        $ressalva = $this->service()->calcular($this->filtros())['ressalva'];

        $this->assertNotEmpty($ressalva);
        $this->assertStringContainsString('REDESIM', $ressalva);
        $this->assertStringContainsString('VIABILIZA', $ressalva);
    }

    public function test_a_ressalva_acompanha_tambem_o_resultado_sem_referencia(): void
    {
        $this->processoInternoCom(2.0);

        $this->assertNotEmpty($this->service()->calcular($this->filtros())['ressalva']);
    }
}
