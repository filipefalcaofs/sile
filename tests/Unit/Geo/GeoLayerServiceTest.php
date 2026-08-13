<?php

namespace Tests\Unit\Geo;

use App\Enums\GeoLayerStatus;
use App\Enums\GeoLayerType;
use App\Models\Activity;
use App\Models\GeoLayer;
use App\Services\Geo\GeoLayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Versionamento de camadas (HU-036 RN-004/RN-005): a abertura de versão fecha
 * a vigente anterior sem apagá-la e o diff é contabilizado. Lógica de linhas
 * (datas + contagem), provada em SQLite — sem PostGIS.
 */
class GeoLayerServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): GeoLayerService
    {
        return app(GeoLayerService::class);
    }

    /**
     * Insere N features com geometria placeholder (texto) — em SQLite a coluna
     * geometry aceita texto; a contagem é o que importa aqui (sem PostGIS).
     */
    private function seedFeatures(GeoLayer $layer, int $quantidade): void
    {
        for ($i = 0; $i < $quantidade; $i++) {
            DB::table('geo_features')->insert([
                'geo_layer_id' => $layer->id,
                'geometry' => 'POINT(0 0)',
                'properties' => json_encode(['i' => $i]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_open_version_cria_camada_vigente(): void
    {
        $layer = $this->service()->openVersion(
            GeoLayerType::Bairro,
            'geosalvador-2024',
            'https://geo.salvador.ba.gov.br/arcgis/rest/services/Bairros/MapServer/0',
        );

        $this->assertSame(GeoLayerType::Bairro, $layer->type);
        $this->assertSame('geosalvador-2024', $layer->version);
        $this->assertSame(GeoLayerStatus::Vigente, $layer->status);
        $this->assertNull($layer->valid_to);
        $this->assertNotNull($layer->valid_from);
        $this->assertSame('geosalvador-2024', $layer->rules_version);
        $this->assertSame(0, $layer->feature_count);
    }

    public function test_segunda_versao_fecha_a_anterior_sem_apagar(): void
    {
        $primeira = $this->service()->openVersion(
            GeoLayerType::Bairro,
            'geosalvador-2023',
            'origem',
            Carbon::parse('2023-01-01'),
        );

        $segunda = $this->service()->openVersion(
            GeoLayerType::Bairro,
            'geosalvador-2024',
            'origem',
            Carbon::parse('2024-06-01'),
        );

        $primeira->refresh();

        // A anterior NÃO foi apagada — foi fechada (histórico/reprodução).
        $this->assertSame(2, GeoLayer::query()->where('type', 'bairro')->count());
        $this->assertSame(GeoLayerStatus::Substituida, $primeira->status);
        $this->assertNotNull($primeira->valid_to);
        $this->assertTrue($primeira->valid_to->equalTo(Carbon::parse('2024-06-01')));

        // A nova é a vigente.
        $this->assertSame(GeoLayerStatus::Vigente, $segunda->status);
        $this->assertNull($segunda->valid_to);

        $vigente = GeoLayer::vigente(GeoLayerType::Bairro)->sole();
        $this->assertTrue($vigente->is($segunda));
    }

    public function test_versao_de_outro_tipo_nao_fecha_a_vigente_de_bairro(): void
    {
        $bairro = $this->service()->openVersion(GeoLayerType::Bairro, 'b-1', 'origem');
        $this->service()->openVersion(GeoLayerType::Via, 'v-1', 'origem');

        $bairro->refresh();

        // Abrir uma camada de via não fecha a vigente de bairro (escopo por type).
        $this->assertSame(GeoLayerStatus::Vigente, $bairro->status);
        $this->assertNull($bairro->valid_to);
    }

    public function test_open_version_e_idempotente_por_tipo_e_versao(): void
    {
        $primeira = $this->service()->openVersion(GeoLayerType::Bairro, 'geosalvador-2024', 'origem');
        $segunda = $this->service()->openVersion(GeoLayerType::Bairro, 'geosalvador-2024', 'origem');

        $this->assertTrue($primeira->is($segunda));
        $this->assertSame(1, GeoLayer::query()->where('type', 'bairro')->count());
    }

    public function test_compute_diff_conta_adicionadas_e_removidas(): void
    {
        $anterior = GeoLayer::factory()->create([
            'type' => GeoLayerType::Bairro,
            'version' => 'antiga',
            'status' => GeoLayerStatus::Substituida,
        ]);
        $this->seedFeatures($anterior, 3);

        $atual = GeoLayer::factory()->create([
            'type' => GeoLayerType::Bairro,
            'version' => 'nova',
        ]);
        $this->seedFeatures($atual, 5);

        $diff = $this->service()->computeDiff($anterior, $atual);

        $this->assertSame(['adicionadas' => 5, 'alteradas' => 0, 'removidas' => 3], $diff);
    }

    public function test_compute_diff_sem_anterior_nao_conta_removidas(): void
    {
        $atual = GeoLayer::factory()->create(['version' => 'inicial']);
        $this->seedFeatures($atual, 4);

        $diff = $this->service()->computeDiff(null, $atual);

        $this->assertSame(['adicionadas' => 4, 'alteradas' => 0, 'removidas' => 0], $diff);
    }

    public function test_finalize_count_atualiza_contagem_de_features(): void
    {
        $layer = $this->service()->openVersion(GeoLayerType::Bairro, 'geosalvador-2024', 'origem');
        $this->seedFeatures($layer, 7);

        $this->service()->finalizeCount($layer);

        $this->assertSame(7, $layer->fresh()->feature_count);
    }

    public function test_audit_load_registra_carga_auditada_com_diff_e_versao(): void
    {
        $layer = $this->service()->openVersion(GeoLayerType::Bairro, 'geosalvador-2024', 'GeoSalvador');

        $this->service()->auditLoad($layer, ['adicionadas' => 5, 'alteradas' => 0, 'removidas' => 0]);

        $activity = Activity::query()
            ->where('log_name', 'territorio')
            ->where('event', 'carga-camada')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame('geosalvador-2024', $activity->rules_version);
        $this->assertSame(['adicionadas' => 5, 'alteradas' => 0, 'removidas' => 0], $activity->properties['diff']);
        $this->assertSame('bairro', $activity->properties['type']);
        $this->assertSame('geosalvador-2024', $activity->properties['version']);
        $this->assertSame('GeoSalvador', $activity->properties['origem']);
    }
}
