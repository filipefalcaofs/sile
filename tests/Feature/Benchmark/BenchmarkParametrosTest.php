<?php

namespace Tests\Feature\Benchmark;

use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * A ingestão do benchmark é integração externa: interruptor e URL entram no
 * catálogo de parâmetros, que é exatamente o caso previsto pela regra de
 * parametrização (muda entre ambientes, a operação desliga de verdade).
 */
class BenchmarkParametrosTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_interruptor_da_integracao_nasce_ligado_no_catalogo(): void
    {
        $this->seed(ParameterSeeder::class);

        $this->assertDatabaseHas('parameters', [
            'key' => 'features.benchmark_redesim',
            'group' => 'features',
            'type' => 'boolean',
            'default_value' => '1',
        ]);
    }

    public function test_url_da_api_de_estatisticas_entra_no_catalogo_de_integracoes(): void
    {
        $this->seed(ParameterSeeder::class);

        $this->assertDatabaseHas('parameters', [
            'key' => 'integrations.redesim_estatistica.base_url',
            'group' => 'integracoes',
            'type' => 'string',
            'default_value' => 'https://estatistica.redesim.gov.br/tempos-abertura-redesim',
        ]);
    }

    public function test_config_aponta_para_a_api_de_estatisticas_da_redesim(): void
    {
        $this->assertSame(
            'https://estatistica.redesim.gov.br/tempos-abertura-redesim',
            config('viabiliza.integrations.redesim_estatistica.base_url'),
        );
    }

    public function test_timeout_acomoda_a_maior_uf(): void
    {
        // São Paulo baixa cerca de 16 MB por competência.
        $this->assertGreaterThanOrEqual(120, config('viabiliza.integrations.redesim_estatistica.timeout'));
    }

    public function test_suite_desliga_a_integracao_por_padrao(): void
    {
        $this->assertFalse(config('viabiliza.features.benchmark_redesim'));
    }
}
