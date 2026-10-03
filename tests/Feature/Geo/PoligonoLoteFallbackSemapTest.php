<?php

namespace Tests\Feature\Geo;

use App\Models\Activity;
use App\Models\ViabilityRequest;
use App\Services\Geo\PoligonoEnderecamentoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cadeia da geometria do lote: endereçamento SEDUR primeiro, cadastro
 * municipal (SEMAP) pela inscrição quando o endereçamento não tem polígono.
 * Nenhuma das duas, o processo segue sem geometria e a zona fica pelo ponto.
 */
class PoligonoLoteFallbackSemapTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sile.features.geoserver_enderecamento' => true,
            'sile.features.arcgis_semap' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function colecao(bool $comFeicao): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => $comFeicao ? [[
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [-38.516331, -12.977852],
                        [-38.516291, -12.977746],
                        [-38.516453, -12.977688],
                        [-38.516492, -12.977795],
                        [-38.516331, -12.977852],
                    ]],
                ],
                'properties' => ['INSCRICAO' => '344672', 'CODLOG' => '212'],
            ]] : [],
        ];
    }

    private function processo(): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->draft()->create(['property_registration' => '344672']);
        $request->forceFill(['cod_log' => '212'])->save();

        return $request->fresh();
    }

    public function test_sem_poligono_no_enderecamento_o_lote_vem_do_cadastro_municipal(): void
    {
        Http::fake([
            '*geoserver.sedur*' => Http::response($this->colecao(false)),
            '*geo.salvador*' => Http::response($this->colecao(true)),
        ]);

        $request = $this->processo();

        $this->assertTrue(app(PoligonoEnderecamentoService::class)->aplicar($request));

        $request->refresh();

        $this->assertSame('Polygon', $request->address_polygon_geojson['type']);
        $this->assertNotNull($request->address_polygon_consulted_at);
        $this->assertTrue(
            Activity::query()->where('event', 'enderecamento-poligono')
                ->get()
                ->contains(fn (Activity $a): bool => ($a->properties['fonte'] ?? null) === 'semap'),
        );
    }

    public function test_enderecamento_com_poligono_nao_consulta_o_cadastro_municipal(): void
    {
        Http::fake([
            '*geoserver.sedur*' => Http::response($this->colecao(true)),
            '*geo.salvador*' => Http::response($this->colecao(true)),
        ]);

        $this->assertTrue(app(PoligonoEnderecamentoService::class)->aplicar($this->processo()));

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'geo.salvador'));
    }

    public function test_nenhuma_fonte_com_poligono_nao_grava_geometria(): void
    {
        Http::fake([
            '*geoserver.sedur*' => Http::response($this->colecao(false)),
            '*geo.salvador*' => Http::response($this->colecao(false)),
        ]);

        $request = $this->processo();

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicar($request));
        $this->assertNull($request->fresh()->address_polygon_geojson);
    }
}
