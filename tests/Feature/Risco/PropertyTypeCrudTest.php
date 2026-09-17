<?php

namespace Tests\Feature\Risco;

use App\Models\PropertyType;
use App\Models\PropertyTypeAlias;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * CRUD dos tipos de imóvel do REGIN (parametrização): o administrador/gestor
 * mantém os tipos de imóvel — dado administrável — pela retaguarda, atrás da
 * permissão manter-tipos-imovel. O code é único e imutável na edição (padrão
 * CNAE/CNPJ); aliases são normalizados na gravação pelo model; a desativação
 * preserva o histórico (toggle, nunca exclui). Toda alteração é auditada
 * (RN-002). Espelha o ViabilityServiceTypeCrudTest.
 */
class PropertyTypeCrudTest extends TestCase
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
        // Analista acessa a gestão mas NÃO tem manter-tipos-imovel.
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/tipos-imovel')
            ->assertForbidden();
    }

    public function test_cria_tipo_com_aliases_normalizados(): void
    {
        $this->actingAs($this->administrador(), 'gestao')->post('/gestao/tipos-imovel', [
            'code' => 'galpao_logistico',
            'label' => 'Galpão logístico',
            'drives_rule' => true,
            'active' => true,
            'aliases' => ['GALPÃO LOGÍSTICO', 'galpao  logistico'],
        ])->assertRedirect();

        $tipo = PropertyType::query()->where('code', 'galpao_logistico')->firstOrFail();
        $this->assertTrue($tipo->drives_rule);
        // normalizado + distinct: as duas grafias viram UM alias
        $this->assertSame(['galpao logistico'], $tipo->aliases->pluck('alias')->all());
    }

    public function test_code_e_imutavel_na_edicao(): void
    {
        $tipo = PropertyType::factory()->create(['code' => 'galpao']);

        $this->actingAs($this->administrador(), 'gestao')->put("/gestao/tipos-imovel/{$tipo->id}", [
            'code' => 'tentativa_de_troca',
            'label' => 'Galpão atualizado',
            'drives_rule' => true,
            'active' => true,
            'aliases' => [],
        ])->assertRedirect();

        $this->assertSame('galpao', $tipo->fresh()->code);
        $this->assertSame('Galpão atualizado', $tipo->fresh()->label);
    }

    public function test_alias_duplicado_entre_tipos_e_rejeitado(): void
    {
        PropertyType::factory()->has(PropertyTypeAlias::factory()->state(['alias' => 'galpao']), 'aliases')->create();

        $this->actingAs($this->administrador(), 'gestao')->post('/gestao/tipos-imovel', [
            'code' => 'outro_tipo',
            'label' => 'Outro tipo',
            'drives_rule' => false,
            'active' => true,
            'aliases' => ['GALPÃO'],
        ])->assertSessionHasErrors('aliases.0');
    }

    public function test_toggle_desativa_sem_excluir_e_audita(): void
    {
        $tipo = PropertyType::factory()->create(['active' => true]);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/tipos-imovel/{$tipo->id}/ativacao")
            ->assertRedirect();

        $this->assertFalse($tipo->fresh()->active);
        $this->assertDatabaseHas('activity_log', ['subject_type' => PropertyType::class, 'subject_id' => $tipo->id]);
    }
}
