<?php

namespace Tests\Feature\Geo;

use App\Models\GeoFeature;
use App\Models\GeoLayer;
use Database\Seeders\GeoLayerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cenário SQLite (suíte): o GeoLayerSeeder NÃO depende de PostGIS — cria as
 * linhas pendente_fonte de zona/lote (comunicando o bloqueio) e não tenta
 * carga espacial. A carga real é provada em GeoLayerSeederPostgisTest
 * (@group postgis).
 */
class GeoLayerSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_em_sqlite_cria_camadas_pendentes_de_zona_e_lote_sem_quebrar(): void
    {
        $this->seed(GeoLayerSeeder::class);

        foreach (['zona', 'lote'] as $tipo) {
            $layer = GeoLayer::query()
                ->where('type', $tipo)
                ->where('status', 'pendente_fonte')
                ->first();

            $this->assertNotNull($layer, "A camada {$tipo} deveria existir como pendente_fonte (bloqueio comunicado).");
            $this->assertSame(0, $layer->feature_count);
            $this->assertSame('pendente-sedur', $layer->source);
        }

        // SQLite não executa PostGIS: nenhuma feição é carregada aqui.
        $this->assertSame(0, GeoFeature::query()->count());
    }

    public function test_seed_em_sqlite_e_idempotente(): void
    {
        $this->seed(GeoLayerSeeder::class);
        $this->seed(GeoLayerSeeder::class);

        $this->assertSame(1, GeoLayer::query()->where('type', 'zona')->count());
        $this->assertSame(1, GeoLayer::query()->where('type', 'lote')->count());
    }
}
