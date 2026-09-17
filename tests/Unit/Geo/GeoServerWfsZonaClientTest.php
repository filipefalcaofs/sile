<?php

namespace Tests\Unit\Geo;

use App\Services\Geo\GeoServerWfsZonaClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cliente WFS do GeoServer SEDUR: identifica a zona urbanística de um ponto
 * por INTERSECTS nas FeatureTypes oficiais. Http::fake — a chamada viva é
 * evidência de homologação, não da suíte.
 */
class GeoServerWfsZonaClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'sile.features.geoserver_zona' => true,
            'sile.integrations.geoserver.backoff_ms' => 0,
            'sile.integrations.geoserver.retries' => 0,
            'sile.integrations.geoserver.type_names' => [
                'louos_zpr3:VM_L_Z_USO_ZPR_3',
            ],
        ]);
    }

    public function test_identifica_zona_pelo_subzona_do_wfs(): void
    {
        Http::fake([
            '*typeName=louos_zpr3*' => Http::response($this->featureCollection('ZPR 3'), 200),
        ]);

        $hit = app(GeoServerWfsZonaClient::class)->identificar(-12.97, -38.51);

        $this->assertSame('identificado', $hit->status);
        $this->assertSame('ZPR-3', $hit->codigo);
        $this->assertSame('ZPR 3', $hit->properties['SUBZONA']);
        $this->assertSame('louos_zpr3:VM_L_Z_USO_ZPR_3', $hit->typeName);
        $this->assertNull($hit->motivo);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'SRID%3D4326')
            || str_contains(urldecode($request->url()), 'SRID=4326'));
    }

    public function test_ponto_fora_de_qualquer_camada_e_nao_encontrado(): void
    {
        Http::fake([
            '*' => Http::response(['type' => 'FeatureCollection', 'features' => []], 200),
        ]);

        $hit = app(GeoServerWfsZonaClient::class)->identificar(-12.97, -38.51);

        $this->assertSame('nao_encontrado', $hit->status);
        $this->assertNull($hit->codigo);
        $this->assertNull($hit->motivo);
    }

    public function test_falha_http_nao_finge_ausencia_de_zona(): void
    {
        Http::fake([
            '*' => Http::response('indisponivel', 503),
        ]);

        $hit = app(GeoServerWfsZonaClient::class)->identificar(-12.97, -38.51);

        $this->assertSame('indisponivel', $hit->status);
        $this->assertNull($hit->codigo);
        $this->assertNotEmpty($hit->motivo);
        $this->assertStringContainsString('GeoServer', (string) $hit->motivo);
    }

    /**
     * @return array<string, mixed>
     */
    private function featureCollection(string $subzona): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'id' => 'VM_L_Z_USO_ZPR_3.1',
                'properties' => [
                    'SUBZONA' => $subzona,
                    'LOCAL' => 'Nazaré',
                ],
            ]],
        ];
    }
}
