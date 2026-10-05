<?php

namespace Tests\Feature\Motor;

use App\Enums\EtapaMotor;
use App\Enums\GeoLayerType;
use App\Enums\SituacaoEtapa;
use App\Models\GeoLayer;
use App\Models\ViabilityRequest;
use App\Services\Geo\SpatialRepository;
use App\Services\Geo\TerritorioProcessoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

class RegistroDasEtapasTerritorioTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FakeSpatialRepository $espacial;

    protected function setUp(): void
    {
        parent::setUp();

        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Via, 'version' => 'logradouro-2024']);

        $this->espacial = new FakeSpatialRepository;
        $this->espacial->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $this->espacial->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZCN-1']]);
        $this->espacial->setNearest(GeoLayerType::Via, [
            'id' => 3,
            'properties' => ['CODLOG' => '958', 'NOME_LOGRADOURO' => 'Rua Luiz Murat'],
            'distancia_m' => 25.9,
        ]);
        $this->app->instance(SpatialRepository::class, $this->espacial);
    }

    private function processoComPonto(): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'property_polygon_geojson' => null,
            'property_point_lat' => -12.977769,
            'property_point_lng' => -38.516404,
        ]);
    }

    public function test_materializar_registra_zona_via_e_bairro_como_ok_quando_identificados(): void
    {
        $processo = $this->processoComPonto();

        app(TerritorioProcessoService::class)->materializar($processo);

        foreach ([EtapaMotor::Zona, EtapaMotor::Via, EtapaMotor::Bairro] as $etapa) {
            $this->assertSame(
                SituacaoEtapa::Ok,
                $processo->etapas()->where('etapa', $etapa->value)->first()?->situacao,
                $etapa->value,
            );
        }
    }

    public function test_materializar_sem_ponto_nao_registra_etapas_de_territorio(): void
    {
        $processo = ViabilityRequest::factory()->create([
            'property_polygon_geojson' => null,
            'property_point_lat' => null,
            'property_point_lng' => null,
        ]);

        app(TerritorioProcessoService::class)->materializar($processo);

        $this->assertSame(0, $processo->etapas()->count());
    }
}
