<?php

namespace Tests\Unit\Geo;

use App\Enums\GeoLayerStatus;
use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Camada geográfica versionada (HU-036 RN-004): a lógica de vigência é pura
 * (datas), provada em SQLite — sem PostGIS. Camadas sem fonte pública
 * (zona/lote) são classificadas como bloqueadas (HU-031/HU-033).
 */
class GeoLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_enum_type_expoe_label_e_fonte_bloqueada(): void
    {
        $this->assertSame('Bairro', GeoLayerType::Bairro->label());
        $this->assertSame('Zona urbanística', GeoLayerType::Zona->label());

        $this->assertFalse(GeoLayerType::Bairro->isBlockedSource());
        $this->assertFalse(GeoLayerType::Via->isBlockedSource());
        $this->assertTrue(GeoLayerType::Zona->isBlockedSource());
        $this->assertTrue(GeoLayerType::Lote->isBlockedSource());
    }

    public function test_enum_status_expoe_label(): void
    {
        $this->assertSame('Vigente', GeoLayerStatus::Vigente->label());
        $this->assertSame('Substituída', GeoLayerStatus::Substituida->label());
        $this->assertSame('Pendente de fonte', GeoLayerStatus::PendenteFonte->label());
    }

    public function test_casts_convertem_tipo_status_e_datas(): void
    {
        $layer = GeoLayer::factory()->create([
            'type' => GeoLayerType::Bairro,
            'status' => GeoLayerStatus::Vigente,
            'valid_from' => '2024-01-01',
        ]);

        $fresh = $layer->fresh();

        $this->assertInstanceOf(GeoLayerType::class, $fresh->type);
        $this->assertInstanceOf(GeoLayerStatus::class, $fresh->status);
        $this->assertInstanceOf(Carbon::class, $fresh->valid_from);
    }

    public function test_scope_vigente_retorna_apenas_camada_sem_valid_to(): void
    {
        GeoLayer::factory()->create([
            'type' => GeoLayerType::Bairro,
            'version' => 'geosalvador-antiga',
            'valid_from' => '2019-01-01',
            'valid_to' => '2021-01-01',
            'status' => GeoLayerStatus::Substituida,
        ]);

        $vigente = GeoLayer::factory()->create([
            'type' => GeoLayerType::Bairro,
            'version' => 'geosalvador-atual',
            'valid_from' => '2021-01-01',
            'valid_to' => null,
            'status' => GeoLayerStatus::Vigente,
        ]);

        $resultado = GeoLayer::vigente(GeoLayerType::Bairro)->get();

        $this->assertCount(1, $resultado);
        $this->assertTrue($resultado->first()->is($vigente));
    }

    public function test_scope_na_data_retorna_a_versao_da_epoca(): void
    {
        $antiga = GeoLayer::factory()->create([
            'type' => GeoLayerType::Bairro,
            'version' => 'geosalvador-antiga',
            'valid_from' => '2019-01-01',
            'valid_to' => '2021-01-01',
            'status' => GeoLayerStatus::Substituida,
        ]);

        GeoLayer::factory()->create([
            'type' => GeoLayerType::Bairro,
            'version' => 'geosalvador-atual',
            'valid_from' => '2021-01-01',
            'valid_to' => null,
            'status' => GeoLayerStatus::Vigente,
        ]);

        $resultado = GeoLayer::naData('bairro', Carbon::parse('2020-01-01'))->get();

        $this->assertCount(1, $resultado);
        $this->assertTrue($resultado->first()->is($antiga));
    }

    public function test_camada_pendente_fonte_e_modelavel_sem_features(): void
    {
        $layer = GeoLayer::factory()->pendenteFonte()->create([
            'type' => GeoLayerType::Zona,
        ]);

        $this->assertSame(GeoLayerStatus::PendenteFonte, $layer->status);
        $this->assertSame('pendente-sedur', $layer->source);
        $this->assertSame(0, $layer->feature_count);
    }
}
