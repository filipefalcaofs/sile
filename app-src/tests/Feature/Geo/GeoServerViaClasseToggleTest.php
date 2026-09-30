<?php

namespace Tests\Feature\Geo;

use App\Models\Parameter;
use App\Support\ApiIntegracaoCatalog;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GeoServerViaClasseToggleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_interruptor_esta_no_catalogo_de_parametros(): void
    {
        $this->seed(ParameterSeeder::class);

        $this->assertDatabaseHas('parameters', [
            'key' => 'features.geoserver_via_classe',
            'group' => 'features',
            'type' => 'boolean',
        ]);
    }

    public function test_interruptor_aparece_na_aba_geoserver(): void
    {
        $aba = ApiIntegracaoCatalog::find('geoserver');

        $chaves = array_column($aba['campos'], 'key');

        $this->assertContains('features.geoserver_via_classe', $chaves);
    }

    public function test_interruptor_nasce_ligado(): void
    {
        $this->seed(ParameterSeeder::class);

        $parametro = Parameter::query()->where('key', 'features.geoserver_via_classe')->sole();

        $this->assertSame('1', $parametro->default_value);
    }
}
