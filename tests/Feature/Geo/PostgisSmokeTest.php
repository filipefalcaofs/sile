<?php

namespace Tests\Feature\Geo;

use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Smoke do stack PostGIS de ponta a ponta (HU-036): prova que ST_Contains e
 * ST_Area executam SQL espacial REAL sobre um polígono inserido. Com o
 * container de dev de pé este teste DEVE reportar PASSOU (não skipped) — o
 * guard do PostgisTestCase impede passe-em-silêncio.
 */
#[Group('postgis')]
class PostgisSmokeTest extends PostgisTestCase
{
    public function test_st_contains_e_st_area_executam_sobre_poligono_real(): void
    {
        $layer = GeoLayer::factory()->vigente()->create([
            'type' => GeoLayerType::Bairro,
            'version' => 'smoke-postgis',
        ]);

        DB::table('geo_features')->insert([
            'geo_layer_id' => $layer->id,
            'geometry' => DB::raw("ST_SetSRID(ST_GeomFromText('POLYGON((-38.6 -13.0, -38.4 -13.0, -38.4 -12.9, -38.6 -12.9, -38.6 -13.0))'), 4326)"),
            'properties' => json_encode(['NOME_BAIRRO' => 'TESTE']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dentro = DB::selectOne('SELECT COUNT(*) c FROM geo_features WHERE ST_Contains(geometry, ST_SetSRID(ST_MakePoint(-38.5, -12.95), 4326))');
        $this->assertSame(1, (int) $dentro->c, 'O ponto interno deveria estar contido no polígono.');

        $fora = DB::selectOne('SELECT COUNT(*) c FROM geo_features WHERE ST_Contains(geometry, ST_SetSRID(ST_MakePoint(0, 0), 4326))');
        $this->assertSame(0, (int) $fora->c, 'O ponto externo NÃO deveria estar contido.');

        $area = DB::selectOne('SELECT ST_Area(geometry) AS a FROM geo_features LIMIT 1');
        $this->assertGreaterThan(0, (float) $area->a, 'A área do polígono deveria ser maior que zero.');
    }

    public function test_indice_gist_existe_em_geo_features(): void
    {
        $indice = DB::selectOne("SELECT indexname FROM pg_indexes WHERE tablename = 'geo_features' AND indexdef ILIKE '%gist%'");

        $this->assertNotNull($indice, 'O índice GiST de geo_features deveria existir no PostgreSQL.');
    }
}
