<?php

namespace Tests\Feature\Expresso;

use App\Models\Parameter;
use App\Models\ViabilityDecision;
use App\Services\Expresso\TvlNumberGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Gerador do número de produto TVL (HU-076 RN-007) provado em SQLite: formato
 * {prefixo}-{N} com DEFAULT INLINE (sem depender do seeder 09-01), sequência
 * única a partir de 3000000, sem reinício anual, e unicidade lógica.
 * A concorrência real sob lockForUpdate (no-op em SQLite) é exercitada no
 * TvlNumberGeneratorPostgisTest.
 */
class TvlNumberGeneratorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function generator(): TvlNumberGenerator
    {
        return new TvlNumberGenerator;
    }

    public function test_primeiro_numero_e_3000000_e_segue_sequencial(): void
    {
        $this->assertSame('TVL-3000000', $this->generator()->generate());
        $this->assertSame('TVL-3000001', $this->generator()->generate());
    }

    public function test_nao_reinicia_na_virada_do_ano(): void
    {
        $this->generator()->generate();

        $this->travelTo(now()->addYear());

        $this->assertSame('TVL-3000001', $this->generator()->generate());
    }

    public function test_numero_sefaz_e_a_parte_numerica(): void
    {
        $decisao = new ViabilityDecision(['tvl_product_number' => $this->generator()->generate()]);

        $this->assertSame(3000000, $decisao->numeroSefaz());
        $this->assertNull((new ViabilityDecision(['tvl_product_number' => 'TVL-2026-000001']))->numeroSefaz());
    }

    public function test_respeita_prefixo_parametrizado(): void
    {
        Parameter::query()->create([
            'key' => 'expresso.tvl.prefixo',
            'group' => 'expresso',
            'type' => 'string',
            'value' => 'TVLX',
            'default_value' => 'TVL',
            'validation_rules' => ['required', 'string'],
            'description' => 'Prefixo do número de produto TVL.',
        ]);

        $this->assertSame('TVLX-3000000', $this->generator()->generate());
    }

    public function test_numeros_unicos_em_sequencia(): void
    {
        $numeros = [];
        for ($i = 0; $i < 50; $i++) {
            $numeros[] = $this->generator()->generate();
        }

        $this->assertCount(50, array_unique($numeros));
    }
}
