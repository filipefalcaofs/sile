<?php

namespace Tests\Feature\Geo;

use App\Models\GeoFeature;
use App\Models\GeoLayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Esquema de geo_features provado em SQLite (:memory:) via RefreshDatabase —
 * é o PHPUnit que prova a migração, sem tocar o banco de dev. O índice GiST
 * NÃO é criado em SQLite (driver-aware), mantendo os 385 testes intactos
 * (Pitfall 1 do RESEARCH).
 */
class GeoSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabela_geo_features_existe_com_colunas(): void
    {
        $this->assertTrue(Schema::hasTable('geo_features'));
        $this->assertTrue(
            Schema::hasColumns('geo_features', ['geo_layer_id', 'geometry', 'properties']),
        );
    }

    public function test_relacao_layer_e_features_funciona(): void
    {
        $layer = GeoLayer::factory()->create();

        // Geometria como texto placeholder: o SQLite não executa PostGIS — a
        // geometria real é exercida pelos testes @group postgis (insert SQL).
        DB::table('geo_features')->insert([
            'geo_layer_id' => $layer->id,
            'geometry' => 'POINT(0 0)',
            'properties' => json_encode(['NOME_BAIRRO' => 'TESTE']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, $layer->features()->count());

        $feature = GeoFeature::first();
        $this->assertTrue($feature->layer->is($layer));
        $this->assertSame('TESTE', $feature->properties['NOME_BAIRRO']);
    }

    public function test_migracao_em_sqlite_nao_lanca_nem_cria_gist(): void
    {
        // Chegar aqui prova que o RefreshDatabase migrou geo_features em SQLite
        // sem tentar o índice GiST (guardado por driver) — suíte intacta.
        $this->assertSame('sqlite', DB::getDriverName());
        $this->assertTrue(Schema::hasTable('geo_features'));
    }
}
