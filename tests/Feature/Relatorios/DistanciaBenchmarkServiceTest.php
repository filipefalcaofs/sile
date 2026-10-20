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

    private function processoDecididoEm(float $horas, string $decididoEm): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => fake()->unique()->numerify('VIA-2026-######'),
            'protocoled_at' => Carbon::parse($decididoEm)->subDay(),
        ]);

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'tvl_product_number' => fake()->unique()->numerify('TVL-2026-######'),
            'decided_at' => Carbon::parse($decididoEm),
        ]);

        $request->forceFill(['viabilidade_horas_uteis' => $horas])->save();
    }

    private function redesimDeSalvador(int $ano, int $mes, float $media): void
    {
        BenchmarkMunicipalityMetric::factory()->salvador()->create([
            'ano' => $ano, 'mes' => $mes, 'media' => $media, 'n' => 600,
        ]);
    }

    public function test_evolucao_compara_o_mes_atual_do_viabiliza_com_o_mes_anterior_da_redesim(): void
    {
        $this->redesimDeSalvador(2026, 9, 21.0);
        $this->processoDecididoEm(2.0, '2026-10-05 10:00:00');
        $this->processoDecididoEm(4.0, '2026-10-06 10:00:00');
        $this->processoDecididoEm(90.0, '2026-09-20 10:00:00');

        $evolucao = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'));

        $this->assertSame(['ano' => 2026, 'mes' => 9], [
            'ano' => $evolucao['anterior']['ano'], 'mes' => $evolucao['anterior']['mes'],
        ]);
        $this->assertEqualsWithDelta(21.0, $evolucao['anterior']['media'], 0.01);
        $this->assertSame(['ano' => 2026, 'mes' => 10], [
            'ano' => $evolucao['atual']['ano'], 'mes' => $evolucao['atual']['mes'],
        ]);
        $this->assertEqualsWithDelta(3.0, $evolucao['atual']['media'], 0.01);
        $this->assertSame(2, $evolucao['atual']['n']);
        $this->assertEqualsWithDelta(-18.0, $evolucao['diferenca_horas'], 0.01);
        $this->assertEqualsWithDelta(-85.71, $evolucao['diferenca_percentual'], 0.01);
        $this->assertSame(DistanciaBenchmarkService::RESSALVA, $evolucao['ressalva']);
    }

    public function test_evolucao_atravessa_a_virada_do_ano(): void
    {
        $this->redesimDeSalvador(2025, 12, 30.0);
        $this->processoDecididoEm(6.0, '2026-01-10 10:00:00');

        $evolucao = $this->service()->evolucaoMensal(Carbon::parse('2026-01-15'));

        $this->assertSame(2025, $evolucao['anterior']['ano']);
        $this->assertSame(12, $evolucao['anterior']['mes']);
        $this->assertEqualsWithDelta(6.0, $evolucao['atual']['media'], 0.01);
    }

    public function test_evolucao_sem_mes_anterior_da_redesim_nao_calcula_diferenca(): void
    {
        $this->processoDecididoEm(2.0, '2026-10-05 10:00:00');

        $evolucao = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'));

        $this->assertNull($evolucao['anterior']['media']);
        $this->assertSame(0, $evolucao['anterior']['n']);
        $this->assertEqualsWithDelta(2.0, $evolucao['atual']['media'], 0.01);
        $this->assertNull($evolucao['diferenca_horas']);
        $this->assertNull($evolucao['diferenca_percentual']);
    }

    public function test_evolucao_sem_processo_no_mes_atual_nao_calcula_diferenca(): void
    {
        $this->redesimDeSalvador(2026, 9, 21.0);

        $evolucao = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'));

        $this->assertNull($evolucao['atual']['media']);
        $this->assertSame(0, $evolucao['atual']['n']);
        $this->assertNull($evolucao['diferenca_horas']);
    }

    public function test_evolucao_com_referencia_zero_nao_divide_por_zero(): void
    {
        $this->redesimDeSalvador(2026, 9, 0.0);
        $this->processoDecididoEm(2.0, '2026-10-05 10:00:00');

        $evolucao = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'));

        $this->assertEqualsWithDelta(2.0, $evolucao['diferenca_horas'], 0.01);
        $this->assertNull($evolucao['diferenca_percentual']);
    }

    public function test_evolucao_traz_a_redesim_do_mes_atual_quando_ja_foi_importada(): void
    {
        $this->redesimDeSalvador(2026, 9, 21.0);
        $this->redesimDeSalvador(2026, 10, 10.0);
        $this->processoDecididoEm(4.0, '2026-10-05 10:00:00');

        $evolucao = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'));

        $this->assertSame(10, $evolucao['redesim_atual']['mes']);
        $this->assertEqualsWithDelta(10.0, $evolucao['redesim_atual']['media'], 0.01);
        $this->assertEqualsWithDelta(-6.0, $evolucao['diferenca_mesmo_mes_horas'], 0.01);
        $this->assertEqualsWithDelta(-60.0, $evolucao['diferenca_mesmo_mes_percentual'], 0.01);
        $this->assertEqualsWithDelta(-17.0, $evolucao['diferenca_horas'], 0.01);
    }

    public function test_evolucao_sem_a_redesim_do_mes_atual_deixa_a_terceira_medida_em_aberto(): void
    {
        $this->redesimDeSalvador(2026, 9, 21.0);
        $this->processoDecididoEm(4.0, '2026-10-05 10:00:00');

        $evolucao = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'));

        $this->assertSame(10, $evolucao['redesim_atual']['mes']);
        $this->assertNull($evolucao['redesim_atual']['media']);
        $this->assertSame(0, $evolucao['redesim_atual']['n']);
        $this->assertNull($evolucao['diferenca_mesmo_mes_horas']);
        $this->assertNull($evolucao['diferenca_mesmo_mes_percentual']);
    }
}
