<?php

namespace Tests\Feature\Geo;

use App\Models\GeoServerLayer;
use App\Services\Geo\ZonaMaiorPorcao;
use App\Services\Geo\ZonaMaiorPorcaoResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZonaMaiorPorcaoResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ca_zn_07_fora_do_postgres_e_indisponivel_sem_consultar_o_geoserver(): void
    {
        config(['sile.features.geoserver_zona' => true, 'sile.features.geoserver_enderecamento' => true]);
        GeoServerLayer::factory()->create();
        Http::fake();

        $resultado = app(ZonaMaiorPorcaoResolver::class)->resolver([
            'type' => 'Polygon',
            'coordinates' => [[[-38.5, -12.98], [-38.499, -12.98], [-38.499, -12.979], [-38.5, -12.98]]],
        ]);

        $this->assertSame(ZonaMaiorPorcao::INDISPONIVEL, $resultado->status);
        $this->assertStringContainsString('PostGIS', (string) $resultado->motivo);
        Http::assertNothingSent();
    }
}
