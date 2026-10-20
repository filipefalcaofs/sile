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
            'viabiliza.features.geoserver_enderecamento' => true,
            'viabiliza.features.arcgis_semap' => true,
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

    public function test_lote_oficial_achado_depois_nao_substitui_o_complementar(): void
    {
        Http::fake(['*geoserver.sedur*' => Http::response($this->colecao(true))]);
        $predio = ['type' => 'MultiPolygon', 'coordinates' => [[[[-38.46, -12.98], [-38.459, -12.98], [-38.459, -12.979], [-38.46, -12.98]]]]];
        $request = ViabilityRequest::factory()->create(['cod_log' => '24574', 'address_number' => '70']);
        $request->forceFill(['address_polygon_geojson' => $predio, 'address_polygon_source' => PoligonoFonte::Overture])->save();

        $this->assertFalse(app(PoligonoEnderecamentoService::class)->aplicar($request->fresh()));

        $request->refresh();
        $this->assertSame($predio, $request->address_polygon_geojson);
        $this->assertSame(PoligonoFonte::Overture, $request->address_polygon_source);
    }

    public function test_lote_oficial_continua_atualizando_lote_oficial(): void
    {
        Http::fake(['*geoserver.sedur*' => Http::response($this->colecao(true))]);
        $antigo = ['type' => 'Polygon', 'coordinates' => [[[-38.5, -12.98], [-38.499, -12.98], [-38.499, -12.979], [-38.5, -12.98]]]];
        $request = ViabilityRequest::factory()->create(['cod_log' => '24574', 'address_number' => '70']);
        $request->forceFill(['address_polygon_geojson' => $antigo, 'address_polygon_source' => PoligonoFonte::Semap])->save();

        $this->assertTrue(app(PoligonoEnderecamentoService::class)->aplicar($request->fresh()));

        $this->assertSame(PoligonoFonte::Chave, $request->fresh()->address_polygon_source);
    }

    public function test_legenda_de_cada_fonte(): void
    {
        $this->assertSame('Contorno tracejado: polígono do endereçamento SEDUR.', PoligonoFonte::Chave->legenda());
        $this->assertSame('Contorno tracejado: polígono do endereçamento SEDUR.', PoligonoFonte::Ponto->legenda());
        $this->assertSame('Contorno tracejado: polígono do cadastro municipal (SEMAP).', PoligonoFonte::Semap->legenda());
        $this->assertSame('Contorno tracejado: polígono do Overture.', PoligonoFonte::Overture->legenda());
        $this->assertSame('Contorno tracejado: polígono da Microsoft.', PoligonoFonte::Microsoft->legenda());
        $this->assertSame('Contorno tracejado: polígono gerado por IA.', PoligonoFonte::Ia->legenda());
    }

    public function test_so_fontes_da_prefeitura_sao_oficiais(): void
    {
        $this->assertTrue(PoligonoFonte::Chave->oficial());
        $this->assertTrue(PoligonoFonte::Semap->oficial());
        $this->assertTrue(PoligonoFonte::Ponto->oficial());
        $this->assertFalse(PoligonoFonte::Overture->oficial());
        $this->assertFalse(PoligonoFonte::Microsoft->oficial());
        $this->assertFalse(PoligonoFonte::Ia->oficial());
    }

    public function test_atribuicao_das_complementares(): void
    {
        $this->assertNull(PoligonoFonte::Chave->atribuicao());
        $this->assertNull(PoligonoFonte::Semap->atribuicao());
        $this->assertSame('© OpenStreetMap contributors, Overture Maps Foundation', PoligonoFonte::Overture->atribuicao());
        $this->assertSame('Microsoft', PoligonoFonte::Microsoft->atribuicao());
        $this->assertSame('Ortofoto: Prefeitura Municipal de Salvador', PoligonoFonte::Ia->atribuicao());
    }
}
