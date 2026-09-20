<?php

namespace Tests\Unit\Analise;

use App\Models\TllValor;
use App\Services\Analise\TllCalculoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Cálculo do valor do DAM da TLL (HU-071 RN-004): o valor é a atividade de
 * maior valor correlacionado à TLL, acrescido da taxa de serviço, aplicando o
 * fator multiplicador quando o CNAE exigir. Sem valor parametrizado para o
 * exercício, o cálculo degrada para pendente (null) — nunca valor inventado.
 */
class TllCalculoServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_um_cnae_valor_e_taxa_de_servico(): void
    {
        TllValor::factory()->create([
            'codigo_tll' => '1.01',
            'exercicio' => 2026,
            'valor' => 1000.00,
            'taxa_servico' => 50.00,
        ]);

        $calculo = app(TllCalculoService::class)->calcular([
            ['cnae' => '4712100', 'codigo_tll' => '1.01', 'exige_fator_multiplicador' => false],
        ], 2026);

        $this->assertNotNull($calculo);
        $this->assertSame('1050.00', $calculo->valor);
        $this->assertSame('1.01', $calculo->codigo_tll);
        $this->assertFalse($calculo->fator_aplicado);
    }

    public function test_varios_cnaes_usa_a_atividade_de_maior_valor(): void
    {
        TllValor::factory()->create(['codigo_tll' => '1.01', 'exercicio' => 2026, 'valor' => 1000.00, 'taxa_servico' => 50.00]);
        TllValor::factory()->create(['codigo_tll' => '2.02', 'exercicio' => 2026, 'valor' => 2000.00, 'taxa_servico' => 80.00]);

        $calculo = app(TllCalculoService::class)->calcular([
            ['cnae' => '4712100', 'codigo_tll' => '1.01', 'exige_fator_multiplicador' => false],
            ['cnae' => '6202300', 'codigo_tll' => '2.02', 'exige_fator_multiplicador' => false],
        ], 2026);

        $this->assertSame('2080.00', $calculo->valor);
        $this->assertSame('2.02', $calculo->codigo_tll);
    }

    public function test_aplica_o_fator_multiplicador_quando_o_cnae_exige(): void
    {
        config(['sile.tll.fator_multiplicador' => 2.0]);

        TllValor::factory()->create(['codigo_tll' => '1.01', 'exercicio' => 2026, 'valor' => 1000.00, 'taxa_servico' => 50.00]);

        $calculo = app(TllCalculoService::class)->calcular([
            ['cnae' => '4712100', 'codigo_tll' => '1.01', 'exige_fator_multiplicador' => true],
        ], 2026);

        $this->assertSame('2100.00', $calculo->valor);
        $this->assertTrue($calculo->fator_aplicado);
    }

    public function test_sem_valor_parametrizado_para_o_exercicio_degrada_para_pendente(): void
    {
        $calculo = app(TllCalculoService::class)->calcular([
            ['cnae' => '4712100', 'codigo_tll' => '1.01', 'exige_fator_multiplicador' => false],
        ], 2026);

        $this->assertNull($calculo);
    }

    public function test_cnae_sem_codigo_tll_e_ignorado_no_calculo(): void
    {
        TllValor::factory()->create(['codigo_tll' => '1.01', 'exercicio' => 2026, 'valor' => 1000.00, 'taxa_servico' => 0]);

        $calculo = app(TllCalculoService::class)->calcular([
            ['cnae' => '4712100', 'codigo_tll' => null, 'exige_fator_multiplicador' => false],
            ['cnae' => '6202300', 'codigo_tll' => '1.01', 'exige_fator_multiplicador' => false],
        ], 2026);

        $this->assertNotNull($calculo);
        $this->assertSame('1.01', $calculo->codigo_tll);
    }
}
