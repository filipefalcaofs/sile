<?php

namespace Tests\Feature\Benchmark;

use App\Models\BenchmarkActivityMetric;
use App\Models\BenchmarkIngestion;
use App\Models\BenchmarkMunicipalityMetric;
use App\Support\Capitais;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Esquema do benchmark federal. A unicidade por competência é o que torna a
 * ingestão idempotente: reprocessar substitui, nunca duplica.
 */
class BenchmarkSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registra_a_ingestao_de_uma_competencia_com_evidencia(): void
    {
        $ingestao = BenchmarkIngestion::factory()->create([
            'uf' => 'BA',
            'ano' => 2026,
            'mes' => 8,
            'linhas_lidas' => 1904,
        ]);

        $this->assertSame('BA', $ingestao->fresh()->uf);
        $this->assertSame(1904, $ingestao->fresh()->linhas_lidas);
        $this->assertNotNull($ingestao->fresh()->hash);
    }

    public function test_nao_aceita_duas_ingestoes_para_a_mesma_competencia_e_uf(): void
    {
        BenchmarkIngestion::factory()->create(['uf' => 'BA', 'ano' => 2026, 'mes' => 8]);

        $this->expectException(QueryException::class);

        BenchmarkIngestion::factory()->create(['uf' => 'BA', 'ano' => 2026, 'mes' => 8]);
    }

    public function test_guarda_a_metrica_agregada_de_um_municipio_na_competencia(): void
    {
        $metrica = BenchmarkMunicipalityMetric::factory()->create([
            'uf' => 'BA',
            'municipio' => 'SALVADOR',
            'ano' => 2026,
            'mes' => 8,
            'n' => 665,
            'media' => 23.24,
            'p50' => 7.0,
            'p90' => 73.0,
        ]);

        $fresco = $metrica->fresh();

        $this->assertSame(665, $fresco->n);
        $this->assertEqualsWithDelta(23.24, $fresco->media, 0.01);
        $this->assertEqualsWithDelta(73.0, $fresco->p90, 0.01);
    }

    public function test_nao_aceita_duas_metricas_para_o_mesmo_municipio_na_competencia(): void
    {
        BenchmarkMunicipalityMetric::factory()->create([
            'uf' => 'BA', 'municipio' => 'SALVADOR', 'ano' => 2026, 'mes' => 8,
        ]);

        $this->expectException(QueryException::class);

        BenchmarkMunicipalityMetric::factory()->create([
            'uf' => 'BA', 'municipio' => 'SALVADOR', 'ano' => 2026, 'mes' => 8,
        ]);
    }

    public function test_guarda_a_metrica_por_atividade_no_escopo(): void
    {
        $metrica = BenchmarkActivityMetric::factory()->create([
            'escopo' => 'salvador',
            'cnae' => '6911701',
            'ano' => 2026,
            'mes' => 8,
            'n' => 29,
            'media' => 54.2,
        ]);

        $this->assertSame('6911701', $metrica->fresh()->cnae);
        $this->assertEqualsWithDelta(54.2, $metrica->fresh()->media, 0.01);
    }

    public function test_lista_as_vinte_e_sete_capitais(): void
    {
        $this->assertCount(27, Capitais::todas());
    }

    public function test_reconhece_salvador_como_capital_da_bahia(): void
    {
        $this->assertTrue(Capitais::ehCapital('BA', 'SALVADOR'));
        $this->assertFalse(Capitais::ehCapital('BA', 'BARREIRAS'));
        $this->assertFalse(Capitais::ehCapital('SP', 'SALVADOR'));
    }

    public function test_normaliza_nome_de_municipio_removendo_acento_e_caixa(): void
    {
        $this->assertSame('SAO PAULO', Capitais::nomeNormalizado('São Paulo'));
        $this->assertSame('BELEM', Capitais::nomeNormalizado(' belém '));
        $this->assertSame('RIO DE JANEIRO', Capitais::nomeNormalizado('Rio  de   Janeiro'));
    }
}
