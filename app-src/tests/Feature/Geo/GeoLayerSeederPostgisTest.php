<?php

namespace Tests\Feature\Geo;

use App\Enums\GeoLayerType;
use App\Models\GeoFeature;
use App\Models\GeoLayer;
use Database\Seeders\GeoLayerSeeder;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Cenário PostGIS (carga real): o seed de dev carrega bairro/via/restrição do
 * snapshot oficial COMMITADO em database/data/geo/ — nunca da rede. Bairro é
 * requisito rígido: snapshot ausente FAZ O TESTE FALHAR (bloqueio da fase),
 * nunca skip silencioso. Zona/lote permanecem pendente_fonte (sem fonte pública).
 */
#[Group('postgis')]
class GeoLayerSeederPostgisTest extends PostgisTestCase
{
    public function test_seed_carrega_bairros_reais_do_snapshot_committado(): void
    {
        // Requisito rígido: o snapshot de bairros DEVE existir (sem fachada).
        $this->assertFileExists(
            database_path('data/geo/bairros.geojson'),
            'Snapshot de bairros (requisito rígido HU-034) ausente — bloqueio da fase, nunca skip.',
        );

        $this->seed(GeoLayerSeeder::class);

        $bairroFeatures = GeoFeature::whereHas('layer', fn ($q) => $q->where('type', 'bairro'))->count();
        $this->assertGreaterThan(100, $bairroFeatures, 'Bairro deveria ter as features reais do GeoSalvador (snapshot ~171).');

        $vigente = GeoLayer::vigente(GeoLayerType::Bairro)->sole();
        $this->assertGreaterThan(0, $vigente->feature_count);

        // Via e restrição também carregam do snapshot real commitado.
        $this->assertGreaterThan(0, GeoFeature::whereHas('layer', fn ($q) => $q->where('type', 'via'))->count());
        $this->assertGreaterThan(0, GeoFeature::whereHas('layer', fn ($q) => $q->where('type', 'restricao'))->count());

        // Zona e lote NÃO têm fonte pública: ficam pendente_fonte, sem geometria.
        $this->assertTrue(GeoLayer::query()->where('type', 'zona')->where('status', 'pendente_fonte')->exists());
        $this->assertTrue(GeoLayer::query()->where('type', 'lote')->where('status', 'pendente_fonte')->exists());
        $this->assertSame(0, GeoFeature::whereHas('layer', fn ($q) => $q->whereIn('type', ['zona', 'lote']))->count());
    }
}
