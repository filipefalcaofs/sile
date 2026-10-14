<?php

namespace Tests\Feature\Geo;

use App\Enums\PoligonoFonte;
use App\Models\ViabilityRequest;
use App\Services\Geo\PoligonoEnderecamentoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O processo guarda de qual fonte veio o polígono do lote: endereçamento por
 * CODLOG + número (chave), cadastro municipal (SEMAP) ou o ponto do imóvel.
 */
class PoligonoFonteTest extends TestCase
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
                'properties' => [
                    'INSCRICAO' => '344672',
                    'CODLOG' => '212',
                    'NUMERACAO_METRICA' => '70',
                    'COMPLEMENTO' => null,
                    'CAT_DE_USO' => 'COMERCIAL',
                    'PADRAOCONS' => 'MÉDIO',
                ],
            ]] : [],
        ];
    }

    public function test_poligono_da_chave_grava_a_fonte_chave(): void
    {
        Http::fake(['*geoserver.sedur*' => Http::response($this->colecao(true))]);

        $request = ViabilityRequest::factory()->draft()->create([
            'cod_log' => '212',
            'address_number' => '70',
        ]);

        $this->assertTrue(app(PoligonoEnderecamentoService::class)->aplicar($request));

        $this->assertSame(PoligonoFonte::Chave, $request->fresh()->address_polygon_source);
    }

    public function test_poligono_do_semap_grava_a_fonte_semap(): void
    {
        Http::fake([
            '*geoserver.sedur*' => Http::response($this->colecao(false)),
            '*geo.salvador*' => Http::response($this->colecao(true)),
        ]);

        $request = ViabilityRequest::factory()->draft()->create([
            'cod_log' => '212',
            'property_registration' => '344672',
        ]);

        $this->assertTrue(app(PoligonoEnderecamentoService::class)->aplicar($request));

        $this->assertSame(PoligonoFonte::Semap, $request->fresh()->address_polygon_source);
    }
}
