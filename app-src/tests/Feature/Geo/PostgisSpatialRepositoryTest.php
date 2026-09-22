<?php

namespace Tests\Feature\Geo;

use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Services\Geo\GeoJsonLayerImporter;
use App\Services\Geo\PostgisSpatialRepository;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * SQL espacial REAL (HU-031/HU-032/HU-034/HU-035) provado contra PostGIS: o
 * repositório identifica o bairro que contém o ponto (ST_Contains), a via mais
 * próxima em metros (ST_DWithin/ST_Distance com ::geography — Pitfall 4) e as
 * restrições incidentes (ST_Intersects), sobre dado real do GeoSalvador e
 * geometrias de amostra inseridas via SQL.
 */
#[Group('postgis')]
class PostgisSpatialRepositoryTest extends PostgisTestCase
{
    private function repository(): PostgisSpatialRepository
    {
        return app(PostgisSpatialRepository::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function amostraBairros(): array
    {
        $path = base_path('tests/Fixtures/geo/bairros-amostra.geojson');

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_containing_feature_identifica_o_bairro_que_contem_o_ponto(): void
    {
        app(GeoJsonLayerImporter::class)->import(
            GeoLayerType::Bairro,
            'amostra',
            'GeoSalvador',
            $this->amostraBairros(),
        );
        $layer = GeoLayer::vigente(GeoLayerType::Bairro)->sole();

        // Golden point: ponto interior garantido do bairro "Colinas de Periperi"
        // (ST_PointOnSurface da própria geometria importada) — exercita ST_Contains
        // sobre dado oficial real.
        $alvo = DB::selectOne(
            "SELECT ST_X(ST_PointOnSurface(geometry)) lng, ST_Y(ST_PointOnSurface(geometry)) lat
               FROM geo_features
              WHERE geo_layer_id = ? AND properties->>'NOME_BAIRRO' = ?",
            [$layer->id, 'Colinas de Periperi'],
        );

        $resultado = $this->repository()->containingFeature($layer, (float) $alvo->lng, (float) $alvo->lat);

        $this->assertNotNull($resultado);
        $this->assertArrayHasKey('id', $resultado);
        $this->assertSame('Colinas de Periperi', $resultado['properties']['NOME_BAIRRO']);

        // Fora de qualquer bairro (oceano, lng/lat 0,0) → null.
        $this->assertNull($this->repository()->containingFeature($layer, 0.0, 0.0));
    }

    public function test_nearest_feature_retorna_a_via_mais_proxima_em_metros(): void
    {
        $layer = GeoLayer::factory()->vigente()->create([
            'type' => GeoLayerType::Via,
            'version' => 'vias-amostra',
        ]);

        // Segmento vertical em Salvador (lng -38.5, lat -12.970 a -12.972).
        DB::table('geo_features')->insert([
            'geo_layer_id' => $layer->id,
            'geometry' => DB::raw("ST_SetSRID(ST_GeomFromText('LINESTRING(-38.5 -12.970, -38.5 -12.972)'), 4326)"),
            'properties' => json_encode(['NOME_LOGRADOURO' => 'Rua Teste']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Ponto a ~32 m a leste da via (0.0003° de lng ≈ 32 m em Salvador) → dentro de 50 m.
        $via = $this->repository()->nearestFeature($layer, -38.4997, -12.971, 50);

        $this->assertNotNull($via);
        $this->assertSame('Rua Teste', $via['properties']['NOME_LOGRADOURO']);
        $this->assertGreaterThan(0.0, $via['distancia_m']);
        $this->assertLessThanOrEqual(50.0, $via['distancia_m']);

        // Mesma via, ponto muito distante → fora do raio de 50 m → null.
        $this->assertNull($this->repository()->nearestFeature($layer, -38.4, -12.9, 50));
    }

    public function test_intersecting_features_retorna_as_restricoes_incidentes(): void
    {
        $layer = GeoLayer::factory()->vigente()->create([
            'type' => GeoLayerType::Restricao,
            'version' => 'restricoes-amostra',
        ]);

        DB::table('geo_features')->insert([
            'geo_layer_id' => $layer->id,
            'geometry' => DB::raw("ST_SetSRID(ST_GeomFromText('POLYGON((-38.6 -13.0, -38.4 -13.0, -38.4 -12.9, -38.6 -12.9, -38.6 -13.0))'), 4326)"),
            'properties' => json_encode(['NOME' => 'ZEIS Teste']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dentro = $this->repository()->intersectingFeatures($layer, -38.5, -12.95);
        $this->assertCount(1, $dentro);
        $this->assertSame('ZEIS Teste', $dentro[0]['properties']['NOME']);

        $fora = $this->repository()->intersectingFeatures($layer, 0.0, 0.0);
        $this->assertSame([], $fora);
    }
}
