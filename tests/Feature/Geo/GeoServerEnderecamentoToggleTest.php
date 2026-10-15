<?php

namespace Tests\Feature\Geo;

use App\Models\Parameter;
use App\Support\ApiIntegracaoCatalog;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GeoServerEnderecamentoToggleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_interruptor_esta_no_catalogo_de_parametros_e_nasce_ligado(): void
    {
        $this->seed(ParameterSeeder::class);

        $parametro = Parameter::query()->where('key', 'features.geoserver_enderecamento')->sole();

        $this->assertSame('features', $parametro->group);
        $this->assertSame('boolean', $parametro->type);
        $this->assertSame('1', $parametro->default_value);
    }

    public function test_interruptor_aparece_na_aba_geoserver(): void
    {
        $chaves = array_column(ApiIntegracaoCatalog::find('geoserver')['campos'], 'key');

        $this->assertContains('features.geoserver_enderecamento', $chaves);
    }

    public function test_camada_do_enderecamento_esta_configurada(): void
    {
        $this->assertSame('enderecamento:VM_ENDERECAMENTO', config('viabiliza.integrations.geoserver.enderecamento.type_name'));
    }
}
