<?php

namespace Tests\Feature\Geo;

use App\Enums\GeoLayerStatus;
use App\Enums\GeoLayerType;
use App\Models\Activity;
use App\Models\GeoLayer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Caminho feliz REAL da importação de camadas pela interface (HU-036,
 * parametrização 3.1): upload de um FeatureCollection GeoJSON de amostra real
 * do GeoSalvador (3 bairros) via HTTP → nova versão vigente criada pelo
 * GeoJsonLayerImporter (PostGIS de verdade), anterior fechada (nunca apagada),
 * feature_count correto e auditoria carga-camada (RN-002/RN-005). Re-import da
 * mesma versão é idempotente.
 */
#[Group('postgis')]
class GeoLayerManagementPostgisTest extends PostgisTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function administrador(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    private function upload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'bairros.geojson',
            (string) file_get_contents(base_path('tests/Fixtures/geo/bairros-amostra.geojson')),
        );
    }

    /**
     * @return array{tipo: string, versao: string, origem: string, arquivo: UploadedFile}
     */
    private function payload(string $versao): array
    {
        return [
            'tipo' => 'bairro',
            'versao' => $versao,
            'origem' => 'GeoSalvador',
            'arquivo' => $this->upload(),
        ];
    }

    public function test_upload_importa_nova_versao_vigente_com_auditoria(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/territorio/camadas', $this->payload('gestao-2026-09'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $layer = GeoLayer::vigente(GeoLayerType::Bairro)->sole();
        $this->assertSame('gestao-2026-09', $layer->version);
        $this->assertSame(GeoLayerStatus::Vigente, $layer->status);
        $this->assertSame('GeoSalvador', $layer->source);
        $this->assertSame(3, $layer->feature_count);

        $total = DB::selectOne('SELECT COUNT(*) c FROM geo_features WHERE geo_layer_id = ?', [$layer->id]);
        $this->assertSame(3, (int) $total->c);

        $activity = Activity::query()
            ->where('log_name', 'territorio')
            ->where('event', 'carga-camada')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame('gestao-2026-09', $activity->rules_version);
        $this->assertSame(3, $activity->properties['diff']['adicionadas']);
    }

    public function test_nova_versao_fecha_a_anterior_sem_apagar(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/territorio/camadas', $this->payload('amostra-2023'))
            ->assertSessionHas('status');

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/territorio/camadas', $this->payload('amostra-2024'))
            ->assertSessionHas('status');

        // Duas cargas coexistem; a anterior foi fechada, não apagada (RN-004).
        $this->assertSame(2, GeoLayer::query()->where('type', 'bairro')->count());

        $anterior = GeoLayer::query()->where('type', 'bairro')->where('version', 'amostra-2023')->sole();
        $this->assertSame(GeoLayerStatus::Substituida, $anterior->status);
        $this->assertNotNull($anterior->valid_to);
        $this->assertSame(3, (int) DB::selectOne('SELECT COUNT(*) c FROM geo_features WHERE geo_layer_id = ?', [$anterior->id])->c);

        $vigente = GeoLayer::vigente(GeoLayerType::Bairro)->sole();
        $this->assertSame('amostra-2024', $vigente->version);
        $this->assertSame(3, $vigente->feature_count);
    }

    public function test_reimport_da_mesma_versao_e_idempotente(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/territorio/camadas', $this->payload('gestao-2026-09'))
            ->assertSessionHas('status');

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/territorio/camadas', $this->payload('gestao-2026-09'))
            ->assertSessionHas('status');

        // Re-import limpa e reinsere as features da versão — nunca duplica.
        $this->assertSame(1, GeoLayer::query()->where('type', 'bairro')->count());

        $layer = GeoLayer::vigente(GeoLayerType::Bairro)->sole();
        $this->assertSame(3, $layer->feature_count);
        $this->assertSame(3, (int) DB::selectOne('SELECT COUNT(*) c FROM geo_features WHERE geo_layer_id = ?', [$layer->id])->c);
    }

    /**
     * Conteúdo que não é JSON: o controller responde flash.error amigável SEM
     * o path do arquivo temporário (detalhe interno, não do admin) e NADA é
     * criado. Só é alcançável no grupo postgis — em SQLite a guarda de driver
     * retorna antes.
     */
    public function test_upload_com_json_invalido_retorna_flash_error_sem_path(): void
    {
        $arquivo = UploadedFile::fake()->createWithContent(
            'bairros.geojson',
            '{ isto nao e json valido ',
        );

        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/territorio/camadas', [
                'tipo' => 'bairro',
                'versao' => 'gestao-2026-09',
                'origem' => 'GeoSalvador',
                'arquivo' => $arquivo,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'O arquivo enviado não é um JSON válido. Verifique o conteúdo e tente novamente.');

        $this->assertStringNotContainsString('/', (string) session('error'));
        $this->assertDatabaseCount('geo_layers', 0);
        $this->assertDatabaseCount('geo_features', 0);
    }

    /**
     * JSON válido que não é um FeatureCollection: o InvalidArgumentException
     * do importador vira flash.error com a mensagem dele e NADA é criado.
     */
    public function test_upload_com_json_que_nao_e_feature_collection_e_rejeitado(): void
    {
        $arquivo = UploadedFile::fake()->createWithContent(
            'bairros.geojson',
            json_encode([
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [-38.5, -12.9]],
                'properties' => [],
            ], JSON_THROW_ON_ERROR),
        );

        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/territorio/camadas', [
                'tipo' => 'bairro',
                'versao' => 'gestao-2026-09',
                'origem' => 'GeoSalvador',
                'arquivo' => $arquivo,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Falha na importação: GeoJSON inválido: esperado um FeatureCollection com features[].');

        $this->assertDatabaseCount('geo_layers', 0);
        $this->assertDatabaseCount('geo_features', 0);
    }
}
