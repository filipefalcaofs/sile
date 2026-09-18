<?php

namespace Tests\Feature\Geo;

use App\Models\GeoServerLayer;
use App\Models\User;
use Database\Seeders\GeoServerLayerSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * CRUD do catálogo de camadas WFS do GeoServer SEDUR (parametrização): o
 * administrador mantém as FeatureTypes de zona pela retaguarda, atrás da
 * permissão manter-territorio — uma zona nova da LOUOS entra por cadastro,
 * sem deploy. O par workspace+type_name é único e imutável na edição (padrão
 * código/CNAE); a desativação preserva o histórico (toggle, nunca exclui) e
 * tudo é auditado (RN-002). Espelha o PropertyTypeCrudTest.
 */
class GeoServerLayerCrudTest extends TestCase
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

    public function test_lista_exige_permissao(): void
    {
        // Analista acessa a gestão e tem consultar-territorio, mas NÃO manter-territorio.
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/territorio/geoserver')
            ->assertForbidden();
    }

    public function test_cria_camada(): void
    {
        $this->actingAs($this->administrador(), 'gestao')->post('/gestao/territorio/geoserver', [
            'workspace' => 'louos_zpr4',
            'type_name' => 'VM_L_Z_USO_ZPR_4',
            'label' => 'ZPR-4',
            'ordem' => 21,
            'ativo' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('geoserver_layers', [
            'workspace' => 'louos_zpr4',
            'type_name' => 'VM_L_Z_USO_ZPR_4',
            'label' => 'ZPR-4',
            'ordem' => 21,
            'ativo' => true,
        ]);
    }

    public function test_workspace_e_type_name_duplicados_sao_rejeitados(): void
    {
        GeoServerLayer::factory()->create([
            'workspace' => 'louos_zpr3',
            'type_name' => 'VM_L_Z_USO_ZPR_3',
        ]);

        $this->actingAs($this->administrador(), 'gestao')->post('/gestao/territorio/geoserver', [
            'workspace' => 'louos_zpr3',
            'type_name' => 'VM_L_Z_USO_ZPR_3',
            'label' => 'Duplicada',
            'ordem' => 99,
            'ativo' => true,
        ])->assertSessionHasErrors('type_name');

        $this->assertSame(1, GeoServerLayer::query()->count());
    }

    /**
     * O par workspace+type_name identifica a FeatureType no GeoServer: é
     * imutável na edição (padrão código/CNAE) — uma troca de camada é
     * desativar a antiga e cadastrar a nova, preservando a trilha.
     */
    public function test_workspace_e_type_name_sao_imutaveis_na_edicao(): void
    {
        $camada = GeoServerLayer::factory()->create([
            'workspace' => 'louos_zpr3',
            'type_name' => 'VM_L_Z_USO_ZPR_3',
        ]);

        $this->actingAs($this->administrador(), 'gestao')->put("/gestao/territorio/geoserver/{$camada->id}", [
            'workspace' => 'tentativa',
            'type_name' => 'TENTATIVA',
            'label' => 'ZPR-3 (raiz)',
            'ordem' => 3,
        ])->assertRedirect();

        $camada->refresh();

        $this->assertSame('louos_zpr3', $camada->workspace);
        $this->assertSame('VM_L_Z_USO_ZPR_3', $camada->type_name);
        $this->assertSame('ZPR-3 (raiz)', $camada->label);
        $this->assertSame(3, $camada->ordem);
    }

    public function test_toggle_desativa_sem_excluir_e_audita(): void
    {
        $camada = GeoServerLayer::factory()->create(['ativo' => true]);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/territorio/geoserver/{$camada->id}/ativacao")
            ->assertRedirect();

        $this->assertFalse($camada->fresh()->ativo);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => GeoServerLayer::class,
            'subject_id' => $camada->id,
        ]);
    }

    /**
     * A carga inicial espelha as 20 FeatureTypes que hoje vivem hardcoded em
     * config/sile.php (integrations.geoserver.type_names) — zona nova passa
     * a entrar por cadastro. Idempotente por workspace+type_name.
     */
    public function test_seeder_carrega_as_camadas_oficiais_de_forma_idempotente(): void
    {
        $this->seed(GeoServerLayerSeeder::class);
        $this->seed(GeoServerLayerSeeder::class);

        $this->assertSame(20, GeoServerLayer::query()->count());
        $this->assertDatabaseHas('geoserver_layers', [
            'workspace' => 'louos_zpr1',
            'type_name' => 'VM_L_Z_USO_ZPR_1',
            'ativo' => true,
            'ordem' => 1,
        ]);
        $this->assertDatabaseHas('geoserver_layers', [
            'workspace' => 'louos_zcmu_municipal_2',
            'type_name' => 'VM_L_Z_USO_ZCMU_2',
            'ativo' => true,
            'ordem' => 20,
        ]);
    }
}
