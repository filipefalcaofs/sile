<?php

namespace Tests\Feature\Geo;

use App\Enums\ZonaFonte;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EnderecamentoSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_processo_tem_as_colunas_do_poligono_do_enderecamento_e_da_fonte_da_zona(): void
    {
        $this->assertTrue(Schema::hasColumns('viability_requests', [
            'address_polygon_geojson',
            'address_polygon_consulted_at',
            'zona_fonte',
        ]));
    }

    public function test_colunas_sao_do_sistema_e_ficam_fora_do_fillable(): void
    {
        $fillable = (new ViabilityRequest)->getFillable();

        $this->assertNotContains('address_polygon_geojson', $fillable);
        $this->assertNotContains('address_polygon_consulted_at', $fillable);
        $this->assertNotContains('zona_fonte', $fillable);
    }

    public function test_casts_devolvem_array_data_e_enum(): void
    {
        $request = ViabilityRequest::factory()->create();
        $poligono = ['type' => 'Polygon', 'coordinates' => [[[-38.5, -12.98], [-38.499, -12.98], [-38.499, -12.979], [-38.5, -12.98]]]];

        $request->forceFill([
            'address_polygon_geojson' => $poligono,
            'address_polygon_consulted_at' => now(),
            'zona_fonte' => ZonaFonte::EnderecamentoMaiorPorcao,
        ])->save();

        $request = $request->fresh();

        $this->assertSame($poligono, $request->address_polygon_geojson);
        $this->assertNotNull($request->address_polygon_consulted_at);
        $this->assertSame(ZonaFonte::EnderecamentoMaiorPorcao, $request->zona_fonte);
        $this->assertSame('Maior porção do lote', ZonaFonte::EnderecamentoMaiorPorcao->label());
        $this->assertSame('Ponto da inscrição', ZonaFonte::Ponto->label());
    }
}
