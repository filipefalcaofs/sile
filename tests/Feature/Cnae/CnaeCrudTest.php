<?php

namespace Tests\Feature\Cnae;

use App\Enums\RuleDomain;
use App\Models\Activity;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\RiskClassification;
use App\Models\RiskCondicionante;
use App\Models\RuleVersion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CnaeCrudTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Grau de risco municipal é salvo direto na versão vigente — sem uma
        // versão do domínio, store()/update() bloqueiam com flash.error.
        RuleVersion::factory()->create();
    }

    /**
     * @return array<string, mixed>
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
            'risco_municipal' => 'baixo_a',
            'exige_rt' => false,
            'exige_rt_se_alto' => false,
            'exige_fator_multiplicador' => false,
            'exige_detalhamento_multiplicador' => false,
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

    public function test_listagem_libera_crud_ao_administrador_e_nao_ao_analista(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/cnaes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/cnaes/index')
                ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('manter-cnaes')));

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/cnaes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/cnaes/index')
                ->where('auth.permissions', fn ($permissions) => ! collect($permissions)->contains('manter-cnaes')));
    }

    public function test_analista_consulta_mas_nao_mantem(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/cnaes')
            ->assertOk();

        $this->actingAs($analista, 'gestao')
            ->post('/gestao/cnaes', $this->validPayload())
            ->assertForbidden();

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/cnaes/{$cnae->id}/editar")
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

    public function test_pagina_de_criacao_carrega_niveis_municipais(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/cnaes/criar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/cnaes/criar')
                ->has('niveisMunicipais', 3));
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

    public function test_criar_cnae_grava_classificacao_de_risco_municipal_direto(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/cnaes', [
                ...$this->validPayload(),
                'risco_municipal' => 'alto',
                'exige_rt' => true,
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

    public function test_edicao_rejeita_codigo_duplicado(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        Cnae::factory()->create(['code' => '9900800']);
        $cnae = Cnae::factory()->create(['code' => '0111301']);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/cnaes/{$cnae->id}", $this->updatePayload($cnae, [
                'code' => '9900-8/00',
            ]))
            ->assertSessionHasErrors('code');

        $this->assertSame('0111301', $cnae->refresh()->code);
    }

    public function test_pagina_de_edicao_carrega_classificacao_e_perguntas_vigentes(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create(['code' => '0111301']);

        $municipal = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->firstOrFail();
        RiskClassification::factory()->create([
            'rule_version_id' => $municipal->id,
            'cnae_code' => '0111301',
            'risco_municipal' => 'alto',
        ]);

        $sanitaria = RuleVersion::factory()->create(['domain' => RuleDomain::RiscoSanitario]);
        RiskCondicionante::factory()->create([
            'rule_version_id' => $sanitaria->id,
            'cnae_code' => '0111301',
            'pergunta' => 'O produto é artesanal?',
        ]);

        $this->actingAs($admin, 'gestao')
            ->get("/gestao/cnaes/{$cnae->id}/editar")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/cnaes/editar')
                ->where('cnae.risco_municipal', 'alto')
                ->where('cnae.code', '0111301')
                ->where('cnae.section_code', $cnae->section_code)
                ->where('cnae.division_code', $cnae->division_code)
                ->where('cnae.group_code', $cnae->group_code)
                ->where('cnae.class_code', $cnae->class_code)
                ->has('condicionantes', 1)
                ->where('condicionantes.0.pergunta', 'O produto é artesanal?'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(Cnae $cnae, array $overrides = []): array
    {
        return [
            'code' => $cnae->formatted_code,
            'description' => $cnae->description,
            'section_code' => $cnae->section_code,
            'section_description' => $cnae->section_description,
            'division_code' => $cnae->division_code,
            'division_description' => $cnae->division_description,
            'group_code' => $cnae->group_code,
            'group_description' => $cnae->group_description,
            'class_code' => $cnae->class_code,
            'class_description' => $cnae->class_description,
            'active' => true,
            'risco_municipal' => 'baixo_a',
            'exige_rt' => false,
            'exige_rt_se_alto' => false,
            'exige_fator_multiplicador' => false,
            'exige_detalhamento_multiplicador' => false,
            ...$overrides,
        ];
    }

    public function test_edicao_atualiza_codigo_hierarquia_e_dados(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create(['code' => '0111301']);
        $municipal = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->firstOrFail();
        RiskClassification::factory()->create([
            'rule_version_id' => $municipal->id,
            'cnae_code' => '0111301',
            'risco_municipal' => 'baixo_b',
        ]);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/cnaes/{$cnae->id}", $this->updatePayload($cnae, [
                'code' => '9900-8/00',
                'description' => 'Denominação ajustada',
                'section_code' => 'U',
                'section_description' => 'Organismos internacionais',
                'division_code' => '99',
                'division_description' => 'Organismos internacionais',
                'group_code' => '99.0',
                'group_description' => 'Organismos internacionais',
                'class_code' => '99.00-8',
                'class_description' => 'Organismos internacionais',
                'risco_municipal' => 'baixo_a',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $cnae->refresh();

        $this->assertSame('9900800', $cnae->code);
        $this->assertSame('Denominação ajustada', $cnae->description);
        $this->assertSame('U', $cnae->section_code);
        $this->assertSame('99.00-8', $cnae->class_code);
        $this->assertDatabaseHas('risk_classifications', [
            'cnae_code' => '9900800',
            'risco_municipal' => 'baixo_a',
        ]);
        $this->assertDatabaseMissing('risk_classifications', [
            'cnae_code' => '0111301',
        ]);
    }

    public function test_edicao_atualiza_grau_de_risco_sem_pedir_quatro_olhos(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create(['code' => '0111301']);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/cnaes/{$cnae->id}", $this->updatePayload($cnae, [
                'risco_municipal' => 'alto',
                'exige_rt' => true,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('risk_classifications', [
            'cnae_code' => '0111301',
            'risco_municipal' => 'alto',
        ]);
        $this->assertTrue($cnae->refresh()->exige_rt);
    }

    public function test_desativacao_logica_preserva_o_registro(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/cnaes/{$cnae->id}", $this->updatePayload($cnae, [
                'active' => false,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cnaes', ['id' => $cnae->id]);
        $this->assertFalse($cnae->refresh()->active);
    }

    public function test_toggle_de_situacao_funciona_com_payload_minimo(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create(['active' => true]);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/cnaes/{$cnae->id}/situacao", ['active' => false])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($cnae->refresh()->active);
    }

    public function test_mudancas_sao_auditadas_com_attribute_changes(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cnae = Cnae::factory()->create();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/cnaes/{$cnae->id}", $this->updatePayload($cnae, [
                'description' => 'Denominação auditável',
            ]))
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

    public function test_permissoes_de_risco_nao_existem_mais(): void
    {
        $this->assertDatabaseMissing('permissions', ['name' => 'consultar-risco']);
        $this->assertDatabaseMissing('permissions', ['name' => 'manter-risco']);
    }

    public function test_rotas_de_risco_standalone_nao_existem_mais(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')->get('/gestao/risco')->assertNotFound();
        $this->actingAs($admin, 'gestao')->get('/gestao/risco/condicionantes')->assertNotFound();
    }
}
