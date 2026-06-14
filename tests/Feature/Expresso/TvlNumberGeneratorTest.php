<?php

namespace Tests\Feature\Expresso;

use App\Models\Parameter;
use App\Services\Expresso\TvlNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gerador do número de produto TVL (HU-076 RN-007) provado em SQLite: formato
 * parametrizável {prefixo}-{AAAA}-{NNNNNN} com DEFAULT INLINE (TVL/6, sem
 * depender do seeder 09-01), sequência reiniciada por ano e unicidade lógica.
 * A concorrência real sob lockForUpdate (no-op em SQLite) é exercitada no
 * TvlNumberGeneratorPostgisTest.
 */
class TvlNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function generator(): TvlNumberGenerator
    {
        return new TvlNumberGenerator;
    }

    public function test_gera_no_formato_parametrizado(): void
    {
        $this->assertSame('TVL-2026-000001', $this->generator()->generate(2026));
        $this->assertSame('TVL-2026-000002', $this->generator()->generate(2026));
    }

    public function test_reinicia_sequencia_por_ano(): void
    {
        $this->generator()->generate(2026);

        $this->assertSame('TVL-2027-000001', $this->generator()->generate(2027));
    }

    public function test_respeita_prefixo_e_padding_parametrizados(): void
    {
        // Grava os parâmetros diretamente (sem depender do seeder do 09-01) —
        // Parameter::saved invalida o cache da chave (efeito sem deploy, HU-014).
        Parameter::query()->create([
            'key' => 'expresso.tvl.prefixo',
            'group' => 'expresso',
            'type' => 'string',
            'value' => 'TVLX',
            'default_value' => 'TVL',
            'validation_rules' => ['required', 'string'],
            'description' => 'Prefixo do número de produto TVL.',
        ]);
        Parameter::query()->create([
            'key' => 'expresso.tvl.padding',
            'group' => 'expresso',
            'type' => 'integer',
            'value' => '4',
            'default_value' => '6',
            'validation_rules' => ['required', 'integer'],
            'description' => 'Casas do contador no número de produto TVL.',
        ]);

        $this->assertSame('TVLX-2026-0001', $this->generator()->generate(2026));
    }

    public function test_numeros_unicos_em_sequencia(): void
    {
        $numeros = [];
        for ($i = 0; $i < 50; $i++) {
            $numeros[] = $this->generator()->generate(2026);
        }

        $this->assertCount(50, array_unique($numeros));
    }
}
