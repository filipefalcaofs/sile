<?php

namespace Tests\Feature\Geo;

use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Services\Geo\LocationValidationService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Sobreposição polígono × lote (HU-037 RN-004) provada contra PostGIS REAL: com
 * uma camada de lote VIGENTE de fixture, o serviço calcula a maior sobreposição
 * com ST_Area(ST_Intersection)/ST_Area e decide pelo limiar. Prova, sem fachada,
 * que a infraestrutura de validação funciona de verdade — pronta para "ligar"
 * quando a SEDUR entregar a base oficial de lotes (a carga muda, a lógica não).
 */
#[Group('postgis')]
class PostgisLocationValidationTest extends PostgisTestCase
{
    private function service(): LocationValidationService
    {
        return app(LocationValidationService::class);
    }

    /**
     * Camada de lote VIGENTE (fixture) com um lote quadrado conhecido
     * (0,01° × 0,01° em Salvador).
     */
    private function seedLoteVigente(): GeoLayer
    {
        $layer = GeoLayer::factory()->vigente()->create([
            'type' => GeoLayerType::Lote,
            'version' => 'lote-fixture',
            'feature_count' => 1,
        ]);

        DB::table('geo_features')->insert([
            'geo_layer_id' => $layer->id,
            'geometry' => DB::raw("ST_SetSRID(ST_GeomFromText('POLYGON((-38.50 -12.97, -38.49 -12.97, -38.49 -12.96, -38.50 -12.96, -38.50 -12.97))'), 4326)"),
            'properties' => json_encode(['INSCRICAO_IMOBILIARIA' => '000123']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $layer;
    }

    /**
     * @param  array<int, array<int, float>>  $ring
     * @return array<string, mixed>
     */
    private function poligono(array $ring): array
    {
        return ['type' => 'Polygon', 'coordinates' => [$ring]];
    }

    public function test_poligono_identico_ao_lote_tem_sobreposicao_total_sem_alerta(): void
    {
        $this->seedLoteVigente();

        $identico = $this->poligono([
            [-38.50, -12.97],
            [-38.49, -12.97],
            [-38.49, -12.96],
            [-38.50, -12.96],
            [-38.50, -12.97],
        ]);

        $result = $this->service()->validate($identico);

        $this->assertSame('validado', $result->status);
        $this->assertFalse($result->alerta);
        $this->assertEqualsWithDelta(100.0, (float) $result->sobreposicaoPercentual, 0.5);
    }

    public function test_poligono_com_pequena_intersecao_fica_abaixo_do_limiar_e_alerta(): void
    {
        $this->seedLoteVigente();

        // Quadrado deslocado para o canto NE do lote: só ~1/4 cai dentro do lote
        // (sobreposição ~25%, abaixo do limiar padrão de 50%).
        $pequeno = $this->poligono([
            [-38.491, -12.961],
            [-38.489, -12.961],
            [-38.489, -12.959],
            [-38.491, -12.959],
            [-38.491, -12.961],
        ]);

        $result = $this->service()->validate($pequeno);

        $this->assertSame('alerta_sobreposicao', $result->status);
        $this->assertTrue($result->alerta);
        $this->assertGreaterThan(0.0, (float) $result->sobreposicaoPercentual);
        $this->assertLessThan(50.0, (float) $result->sobreposicaoPercentual);
    }
}
