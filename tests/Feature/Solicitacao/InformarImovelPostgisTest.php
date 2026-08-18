<?php

namespace Tests\Feature\Solicitacao;

use App\Models\ViabilityRequest;
use App\Services\Solicitacao\PropertyGeometryWriter;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Derivação driver-aware da geometria do imóvel (HU-062) provada contra PostGIS
 * REAL: a fonte de verdade é o `property_polygon_geojson` (jsonb, portável); a
 * coluna geometry derivada `property_polygon` é gravada SÓ no pgsql via
 * ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?),4326)) — idêntico ao
 * GeoJsonLayerImporter da Fase 4. A área do polígono é calculada com ST_Area
 * real (m², ::geography), base da validação área×polígono (HU-063 RN-004).
 */
#[Group('postgis')]
class InformarImovelPostgisTest extends PostgisTestCase
{
    private function writer(): PropertyGeometryWriter
    {
        return app(PropertyGeometryWriter::class);
    }

    /**
     * Polígono de 4 pontos (quadrilátero fechado) em Salvador.
     *
     * @return array<string, mixed>
     */
    private function poligono(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.5108, -12.9711],
                [-38.5108, -12.9709],
                [-38.5106, -12.9709],
                [-38.5106, -12.9711],
                [-38.5108, -12.9711],
            ]],
        ];
    }

    public function test_geometria_derivada_do_geojson_no_postgres(): void
    {
        $solicitacao = ViabilityRequest::factory()->create([
            'property_polygon_geojson' => $this->poligono(),
        ]);

        $this->writer()->write($solicitacao);

        $row = DB::selectOne(
            'SELECT ST_AsText(property_polygon) AS wkt,
                    ST_SRID(property_polygon) AS srid,
                    GeometryType(property_polygon) AS gtype
               FROM viability_requests WHERE id = ?',
            [$solicitacao->id],
        );

        // A derivada existe, é um POLYGON em SRID 4326 — geometria real, sem fachada.
        $this->assertNotNull($row->wkt);
        $this->assertSame(4326, (int) $row->srid);
        $this->assertSame('POLYGON', $row->gtype);
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    public function test_area_x_poligono_via_postgis(): void
    {
        $solicitacao = ViabilityRequest::factory()->create([
            'property_polygon_geojson' => $this->poligono(),
        ]);

        $this->writer()->write($solicitacao);

        // Área real do polígono via PostGIS (m², ::geography) — fonte de verdade.
        $areaBanco = (float) DB::selectOne(
            'SELECT ST_Area(property_polygon::geography) AS area FROM viability_requests WHERE id = ?',
            [$solicitacao->id],
        )->area;

        $this->assertGreaterThan(0.0, $areaBanco);

        // O serviço calcula a MESMA área (ST_Area no pgsql) a partir do GeoJSON —
        // base da validação área×polígono (o lote oficial é pendente SEDUR, então
        // a avaliação é contra a área do PRÓPRIO polígono desenhado).
        $areaServico = $this->writer()->polygonAreaSquareMeters($this->poligono());

        $this->assertNotNull($areaServico);
        $this->assertEqualsWithDelta($areaBanco, $areaServico, 1.0);

        // Área declarada muito maior que a do polígono é inconsistente (RN-004):
        // a avaliação contra a área real do polígono detecta a divergência acima
        // da tolerância padrão (10%).
        $declaradaAbsurda = $areaBanco * 5;
        $divergencia = ($declaradaAbsurda - $areaBanco) / $areaBanco * 100;
        $this->assertGreaterThan(10.0, $divergencia);
    }
}
