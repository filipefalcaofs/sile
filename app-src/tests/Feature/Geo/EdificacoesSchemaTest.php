<?php

namespace Tests\Feature\Geo;

use App\Enums\FonteEdificacao;
use App\Enums\GeoLayerType;
use App\Enums\PoligonoFonte;
use App\Enums\PropertyLocationPrecision;
use App\Enums\StatusResolucaoPoligono;
use App\Models\LotPolygonResolution;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EdificacoesSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_tabelas_e_coluna_de_precisao_existem(): void
    {
        $this->assertTrue(Schema::hasColumns('building_footprints', [
            'source', 'source_id', 'release', 'geometry', 'area_m2', 'confidence', 'source_datasets',
            'properties', 'duplicate_of_id', 'import_id', 'retired_at', 'validated_at', 'validation_notes',
        ]));
        $this->assertTrue(Schema::hasColumns('building_footprint_imports', [
            'source', 'release', 'checksum', 'status', 'counters', 'started_at', 'finished_at', 'error',
        ]));
        $this->assertTrue(Schema::hasColumns('lot_polygon_resolutions', [
            'viability_request_id', 'source', 'building_footprint_id', 'source_id', 'release', 'status', 'method',
            'official', 'area_m2', 'confidence', 'point_lat', 'point_lng', 'point_origin', 'reason', 'latency_ms',
        ]));
        $this->assertTrue(Schema::hasColumn('viability_requests', 'property_location_precision'));
    }

    public function test_casts_da_resolucao_e_da_precisao(): void
    {
        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['property_location_precision' => PropertyLocationPrecision::Numero])->save();

        $resolucao = LotPolygonResolution::factory()->create([
            'viability_request_id' => $request->id,
            'source' => PoligonoFonte::Overture,
            'status' => StatusResolucaoPoligono::Encontrado,
        ]);

        $this->assertSame(PropertyLocationPrecision::Numero, $request->fresh()->property_location_precision);
        $this->assertSame(PoligonoFonte::Overture, $resolucao->fresh()->source);
        $this->assertSame(StatusResolucaoPoligono::Encontrado, $resolucao->fresh()->status);
        $this->assertFalse($resolucao->fresh()->official);
    }

    public function test_enums_novos(): void
    {
        $this->assertSame(PoligonoFonte::Overture, FonteEdificacao::Overture->paraPoligonoFonte());
        $this->assertSame(PoligonoFonte::Ia, FonteEdificacao::Ia->paraPoligonoFonte());
        $this->assertSame('limite_municipio', GeoLayerType::LimiteMunicipio->value);
        $this->assertSame('Limite do município', GeoLayerType::LimiteMunicipio->label());
        $this->assertFalse(GeoLayerType::LimiteMunicipio->isBlockedSource());
    }
}
