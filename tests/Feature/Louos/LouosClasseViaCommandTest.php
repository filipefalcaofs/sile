<?php

namespace Tests\Feature\Louos;

use App\Models\Parameter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LouosClasseViaCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Parameter::query()->updateOrCreate(
            ['key' => 'features.geoserver_via_classe'],
            [
                'group' => 'features',
                'type' => 'boolean',
                'value' => '1',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Consulta da classificação viária no GeoServer.',
            ],
        );
        cache()->forget('viabiliza.parameters.features.geoserver_via_classe');
    }

    public function test_comando_mostra_a_classe_oficial_do_codlog(): void
    {
        Http::fake(['*' => Http::response([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['CODLOG' => 822, 'LOGRADOURO' => 'Avenida Joana Angélica', 'CLASSIFICACAO_VIARIA' => 'VC-I'],
            ]],
        ], 200)]);

        $this->artisan('louos:classe-via', ['codlog' => '822'])
            ->expectsOutputToContain('VC I')
            ->assertExitCode(0);
    }

    public function test_comando_falha_quando_o_geoserver_nao_responde(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);

        $this->artisan('louos:classe-via', ['codlog' => '822'])
            ->assertExitCode(1);
    }
}
