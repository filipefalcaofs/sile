<?php

namespace Tests\Feature\Geo;

use App\Enums\GeoLayerStatus;
use App\Enums\GeoLayerType;
use App\Models\Activity;
use App\Models\GeoLayer;
use App\Services\Geo\GeoJsonLayerImporter;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Carga de GeoJSON oficial em geo_features (HU-036) provada contra PostGIS
 * real com dado de amostra REAL do GeoSalvador (3 bairros): geometria válida,
 * idempotência por (type, version), preservação de versões anteriores e diff
 * auditado.
 */
#[Group('postgis')]
class ImportGeoLayerTest extends PostgisTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function amostraBairros(): array
    {
        $path = base_path('tests/Fixtures/geo/bairros-amostra.geojson');

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    private function importer(): GeoJsonLayerImporter
    {
        return app(GeoJsonLayerImporter::class);
    }

    public function test_importa_geojson_cria_camada_vigente_com_features_validas(): void
    {
        $relatorio = $this->importer()->import(
            GeoLayerType::Bairro,
            'geosalvador-amostra',
            'https://geo.salvador.ba.gov.br/arcgis/rest/services/Bairros/MapServer/0',
            $this->amostraBairros(),
        );

        $this->assertSame(3, $relatorio['lidas']);
        $this->assertSame(3, $relatorio['inseridas']);
        $this->assertSame([], $relatorio['invalidas']);
        $this->assertSame(3, $relatorio['feature_count']);

        $layer = GeoLayer::vigente(GeoLayerType::Bairro)->sole();
        $this->assertSame(GeoLayerStatus::Vigente, $layer->status);
        $this->assertSame(3, $layer->feature_count);

        $total = DB::selectOne('SELECT COUNT(*) c FROM geo_features WHERE geo_layer_id = ?', [$layer->id]);
        $this->assertSame(3, (int) $total->c);

        // Toda geometria carregada é válida (ST_MakeValid na carga — Pitfall 8).
        $validas = DB::selectOne('SELECT COUNT(*) c FROM geo_features WHERE geo_layer_id = ? AND ST_IsValid(geometry)', [$layer->id]);
        $this->assertSame(3, (int) $validas->c);

        // Geometria realmente espacial: SRID 4326 e área > 0.
        $srid = DB::selectOne('SELECT DISTINCT ST_SRID(geometry) s FROM geo_features WHERE geo_layer_id = ?', [$layer->id]);
        $this->assertSame(4326, (int) $srid->s);
    }

    public function test_reimportar_mesma_versao_e_idempotente_nao_duplica(): void
    {
        $this->importer()->import(GeoLayerType::Bairro, 'geosalvador-amostra', 'origem', $this->amostraBairros());
        $relatorio = $this->importer()->import(GeoLayerType::Bairro, 'geosalvador-amostra', 'origem', $this->amostraBairros());

        // Re-import limpa e reinsere as features da versão — nunca duplica.
        $this->assertSame(1, GeoLayer::query()->where('type', 'bairro')->count());
        $this->assertSame(3, $relatorio['inseridas']);

        $layer = GeoLayer::vigente(GeoLayerType::Bairro)->sole();
        $this->assertSame(3, (int) DB::selectOne('SELECT COUNT(*) c FROM geo_features WHERE geo_layer_id = ?', [$layer->id])->c);
        $this->assertSame(3, $layer->feature_count);
    }

    public function test_importar_nova_versao_fecha_anterior_e_mantem_features(): void
    {
        $v1 = $this->importer()->import(GeoLayerType::Bairro, 'amostra-2023', 'origem', $this->amostraBairros());
        $this->importer()->import(GeoLayerType::Bairro, 'amostra-2024', 'origem', $this->amostraBairros());

        // Duas cargas coexistem; a anterior foi fechada, não apagada (RN-004).
        $this->assertSame(2, GeoLayer::query()->where('type', 'bairro')->count());

        $anterior = GeoLayer::query()->where('type', 'bairro')->where('version', 'amostra-2023')->sole();
        $this->assertSame(GeoLayerStatus::Substituida, $anterior->status);
        $this->assertNotNull($anterior->valid_to);

        // As features da versão antiga continuam disponíveis (reprodução).
        $this->assertSame(3, (int) DB::selectOne('SELECT COUNT(*) c FROM geo_features WHERE geo_layer_id = ?', [$anterior->id])->c);

        $vigente = GeoLayer::vigente(GeoLayerType::Bairro)->sole();
        $this->assertSame('amostra-2024', $vigente->version);
        $this->assertSame(3, (int) DB::selectOne('SELECT COUNT(*) c FROM geo_features WHERE geo_layer_id = ?', [$vigente->id])->c);

        // 3 (antiga) + 3 (nova) = 6 features no total.
        $this->assertSame(6, (int) DB::selectOne('SELECT COUNT(*) c FROM geo_features')->c);
    }

    public function test_carga_e_auditada_com_diff_e_versao(): void
    {
        $this->importer()->import(GeoLayerType::Bairro, 'geosalvador-amostra', 'GeoSalvador', $this->amostraBairros());

        $activity = Activity::query()
            ->where('log_name', 'territorio')
            ->where('event', 'carga-camada')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame('geosalvador-amostra', $activity->rules_version);
        $this->assertSame(3, $activity->properties['diff']['adicionadas']);
        $this->assertSame(0, $activity->properties['diff']['alteradas']);
        $this->assertSame(0, $activity->properties['diff']['removidas']);
    }

    public function test_comando_geo_importar_carrega_o_arquivo_de_ponta_a_ponta(): void
    {
        $this->artisan('geo:importar', [
            'type' => 'bairro',
            'arquivo' => base_path('tests/Fixtures/geo/bairros-amostra.geojson'),
            '--versao' => 'geosalvador-amostra',
            '--source' => 'GeoSalvador',
        ])
            ->expectsOutputToContain('Inseridas: 3')
            ->assertSuccessful();

        $layer = GeoLayer::vigente(GeoLayerType::Bairro)->sole();
        $this->assertSame(3, $layer->feature_count);
    }
}
