<?php

namespace Tests\Feature\Geo;

use App\Enums\GeoLayerStatus;
use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Gestão de camadas geográficas pela interface (HU-036, parametrização 3.1) —
 * parte SQLite: permissão (manter-territorio), validação do upload e a guarda
 * de driver honesta (anti-fachada): o importador é PostGIS-only (ST_*), então
 * fora do pgsql o store recusa com flash.error e NÃO cria nada — nunca finge
 * importação. O caminho feliz real está no GeoLayerManagementPostgisTest.
 */
class GeoLayerManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function administrador(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function geojson(string $nome = 'bairros.geojson'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $nome,
            (string) file_get_contents(base_path('tests/Fixtures/geo/bairros-amostra.geojson')),
        );
    }

    public function test_index_exige_permissao(): void
    {
        // Analista acessa a gestão e tem consultar-territorio, mas NÃO manter-territorio.
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/territorio/camadas')
            ->assertForbidden();
    }

    public function test_importacao_exige_permissao(): void
    {
        $this->actingAs($this->analista(), 'gestao')
            ->post('/gestao/territorio/camadas', [
                'tipo' => 'bairro',
                'versao' => 'gestao-2026-09',
                'origem' => 'GeoSalvador',
                'arquivo' => $this->geojson(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('geo_layers', 0);
    }

    public function test_validacao_exige_o_arquivo(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/territorio/camadas', [
                'tipo' => 'bairro',
                'versao' => 'gestao-2026-09',
            ])
            ->assertSessionHasErrors('arquivo');
    }

    public function test_validacao_rejeita_tipo_invalido(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/territorio/camadas', [
                'tipo' => 'planeta',
                'versao' => 'gestao-2026-09',
                'arquivo' => $this->geojson(),
            ])
            ->assertSessionHasErrors('tipo');
    }

    public function test_validacao_exige_versao_nao_vazia(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/territorio/camadas', [
                'tipo' => 'bairro',
                'versao' => '   ',
                'arquivo' => $this->geojson(),
            ])
            ->assertSessionHasErrors('versao');
    }

    /**
     * Guarda de driver honesta (anti-fachada): o importador usa funções ST_*
     * do PostGIS. Em conexão não-pgsql (SQLite de teste/dev), o store recusa
     * com flash.error claro e NENHUMA camada é criada — nunca uma importação
     * fingida.
     */
    public function test_guarda_de_driver_recusa_importacao_fora_do_postgis(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/territorio/camadas', [
                'tipo' => 'bairro',
                'versao' => 'gestao-2026-09',
                'origem' => 'GeoSalvador',
                'arquivo' => $this->geojson(),
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'A importação de camadas exige o banco PostGIS (produção/homologação).');

        $this->assertDatabaseCount('geo_layers', 0);
        $this->assertDatabaseCount('geo_features', 0);
    }

    /**
     * O index agrupa as camadas pelos 5 tipos do enum (label), com a versão
     * vigente destacada e o histórico (feature_count/source/vigência) — a
     * leitura não usa funções espaciais, então roda em SQLite.
     */
    public function test_index_agrupa_por_tipo_com_vigente_e_historico(): void
    {
        $substituida = GeoLayer::factory()->create([
            'type' => GeoLayerType::Bairro,
            'version' => 'geosalvador-2023',
            'status' => GeoLayerStatus::Substituida,
            'valid_from' => '2023-01-01',
            'valid_to' => '2024-01-01',
            'feature_count' => 100,
        ]);
        $vigente = GeoLayer::factory()->vigente()->create([
            'type' => GeoLayerType::Bairro,
            'version' => 'geosalvador-2024',
            'valid_from' => '2024-01-01',
            'feature_count' => 102,
        ]);

        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/territorio/camadas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/territorio/camadas')
                ->has('grupos', 5)
                ->where('grupos.0.tipo', 'bairro')
                ->where('grupos.0.label', 'Bairro')
                ->where('grupos.0.vigente.id', $vigente->id)
                ->where('grupos.0.vigente.version', 'geosalvador-2024')
                ->where('grupos.0.vigente.feature_count', 102)
                ->has('grupos.0.versoes', 2)
                ->where('grupos.1.tipo', 'zona')
                ->where('grupos.1.vigente', null)
                ->where('grupos.4.tipo', 'restricao')
            );

        $this->assertNotNull($substituida);
    }
}
