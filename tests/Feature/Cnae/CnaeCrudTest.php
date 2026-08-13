<?php

namespace Tests\Feature\Cnae;

use App\Models\Activity;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\RuleVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CnaeCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'code' => '9900-8/00',
            'description' => 'Organismos internacionais e outras instituições extraterritoriais',
            'section_code' => 'U',
            'section_description' => 'Organismos internacionais e outras instituições extraterritoriais',
            'division_code' => '99',
            'division_description' => 'Organismos internacionais e outras instituições extraterritoriais',
            'group_code' => '99.0',
            'group_description' => 'Organismos internacionais e outras instituições extraterritoriais',
            'class_code' => '99.00-8',
            'class_description' => 'Organismos internacionais e outras instituições extraterritoriais',
        ];
    }

    public function test_administrador_lista_cnaes_paginados(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        Cnae::factory()->count(3)->create();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/cnaes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/cnaes/index')
                ->has('cnaes.data', 3)
                ->has('cnaes.data.0', fn (Assert $item) => $item
                    ->hasAll(['id', 'code', 'formatted_code', 'description', 'active'])
                    ->etc()));
    }

    public function test_busca_filtra_por_codigo_ou_denominacao(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        Cnae::factory()->create(['code' => '5611201', 'description' => 'Restaurantes e similares']);
        Cnae::factory()->create(['code' => '0111301', 'description' => 'Cultivo de arroz']);

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/cnaes?search=restaurante')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cnaes.data', 1)
                ->where('cnaes.data.0.code', '5611201'));

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/cnaes?search=0111-3')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cnaes.data', 1)
                ->where('cnaes.data.0.code', '0111301'));
    }

    public function test_analista_consulta_mas_nao_mantem(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/cnaes')
            ->assertOk();

        $this->actingAs($analista, 'gestao')
            ->post('/gestao/cnaes', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
            'causer_id' => $analista->id,
        ]);
    }

    public function test_cidadao_nao_acessa_cnaes(): void
    {
        $cidadao = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($cidadao, 'gestao')
            ->get('/gestao/cnaes')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
            'causer_id' => $cidadao->id,
        ]);
    }

    public function test_cria_cnae_manual_normalizando_codigo(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/cnaes', $this->validPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('cnaes', [
            'code' => '9900800',
            'description' => 'Organismos internacionais e outras instituições extraterritoriais',
        ]);
    }

    public function test_codigo_duplicado_e_malformado_bloqueados(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        Cnae::factory()->create(['code' => '9900800']);

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/cnaes', $this->validPayload())
            ->assertSessionHasErrors('code');

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/cnaes', [...$this->validPayload(), 'code' => '123'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Cnae::count());
    }

    public function test_edicao_atualiza_dados_mas_nunca_o_codigo(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create(['code' => '0111301']);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/cnaes/{$cnae->id}", [
                'code' => '9999999',
                'description' => 'Denominação ajustada',
                'active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $cnae->refresh();

        $this->assertSame('0111301', $cnae->code);
        $this->assertSame('Denominação ajustada', $cnae->description);
    }

    public function test_desativacao_logica_preserva_o_registro(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/cnaes/{$cnae->id}", [
                'description' => $cnae->description,
                'active' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cnaes', ['id' => $cnae->id]);
        $this->assertFalse($cnae->refresh()->active);
    }

    public function test_mudancas_sao_auditadas_com_attribute_changes(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/cnaes/{$cnae->id}", [
                'description' => 'Denominação auditável',
                'active' => true,
            ])
            ->assertRedirect();

        $activity = Activity::where('event', 'updated')
            ->where('subject_type', Cnae::class)
            ->where('subject_id', $cnae->id)
            ->first();

        $this->assertNotNull($activity, 'Esperava activity de atualização do CNAE');
        $this->assertArrayHasKey('description', $activity->attribute_changes['attributes'] ?? []);
    }

    public function test_exclusao_de_cnae_e_auditada(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create();

        $this->actingAs($admin, 'gestao')
            ->delete("/gestao/cnaes/{$cnae->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('cnaes', ['id' => $cnae->id]);

        $activity = Activity::where('event', 'deleted')
            ->where('subject_type', Cnae::class)
            ->where('subject_id', $cnae->id)
            ->first();

        $this->assertNotNull($activity, 'Esperava activity de exclusão do CNAE');
    }

    public function test_exclusao_de_cnae_vinculado_a_empresa_e_bloqueada(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $company = Company::factory()->create();
        $cnae = Cnae::factory()->create();
        $company->cnaes()->attach($cnae->id, ['is_primary' => true]);

        $this->actingAs($admin, 'gestao')
            ->delete("/gestao/cnaes/{$cnae->id}")
            ->assertRedirect()
            ->assertSessionHas('error', 'CNAE vinculado a empresas não pode ser excluído.');

        $this->assertDatabaseHas('cnaes', ['id' => $cnae->id]);

        $activity = Activity::where('event', 'deleted')
            ->where('subject_type', Cnae::class)
            ->where('subject_id', $cnae->id)
            ->first();

        $this->assertNull($activity, 'Não deveria haver activity de exclusão para CNAE vinculado');
    }

    public function test_criar_cnae_grava_classificacao_de_risco_municipal_direto(): void
    {
        RuleVersion::factory()->create();

        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/cnaes', [
                ...$this->validPayload(),
                'risco_municipal' => 'alto',
                'exige_rt' => true,
                'exige_rt_se_alto' => false,
                'exige_fator_multiplicador' => false,
                'exige_detalhamento_multiplicador' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $cnae = Cnae::where('code', '9900800')->firstOrFail();

        $this->assertTrue($cnae->exige_rt);
        $this->assertDatabaseHas('risk_classifications', [
            'cnae_code' => '9900800',
            'risco_municipal' => 'alto',
        ]);
    }
}
