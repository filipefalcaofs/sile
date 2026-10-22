<?php

namespace Tests\Feature\Roles;

use App\Models\Activity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageRolesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    public function test_administrador_lista_perfis_com_permissoes_e_vinculos(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/perfis?per_page=50')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/perfis/index')
                ->has('roles.data', 19)
                ->has('roles.data.0', fn (Assert $item) => $item
                    ->hasAll(['id', 'name', 'permissions', 'users_count', 'structural']))
                ->where(
                    'roles.data',
                    fn ($roles) => collect($roles)->contains(
                        fn ($role) => $role['name'] === 'administrador'
                            && $role['structural'] === true
                            && $role['users_count'] === 1
                    )
                )
                ->has('permissions', 42)
                ->where('roles.total', 19)
                ->where('roles.per_page', 50)
                ->where('filters.search', '')
                ->where('filters.per_page', 50)
                ->has('perPageOptions', 4)
                ->has('permissionCatalog', 42)
                ->where(
                    'permissionCatalog',
                    fn ($catalog) => collect($catalog)->contains(
                        fn ($item) => $item['name'] === 'consultar-cnaes'
                            && $item['label'] === 'Consultar CNAEs'
                            && $item['description'] !== ''
                    )
                )
                ->where(
                    'permissionCatalog',
                    fn ($catalog) => collect($catalog)->contains(
                        fn ($item) => $item['name'] === 'manter-cnaes'
                            && $item['label'] === 'Cadastrar e editar CNAEs'
                            && $item['description'] !== ''
                    )
                )
                ->where(
                    'permissionCatalog',
                    fn ($catalog) => collect($catalog)->every(
                        fn ($item) => $item['group'] !== 'Outras' && $item['label'] !== $item['name']
                    )
                ));
    }

    public function test_busca_por_nome_filtra_a_listagem_e_preserva_o_termo(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/perfis?search=ADMINISTRADOR')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'ADMINISTRADOR')
                ->where('roles.total', 1)
                ->where('roles.data.0.name', 'administrador')
                ->where('roles.data.0.structural', true));
    }

    public function test_paginacao_mantem_a_busca_e_a_pagina_pedida(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/perfis?per_page=10&page=2&search=a')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'a')
                ->where('filters.per_page', 10)
                ->where('roles.per_page', 10)
                ->where('roles.current_page', 2)
                ->where(
                    'roles.data',
                    fn ($data) => collect($data)->isNotEmpty()
                        && collect($data)->every(
                            fn ($role) => str_contains(mb_strtolower($role['name']), 'a'),
                        ),
                )
                ->has('perPageOptions', 4));
    }

    public function test_quantidade_por_pagina_fora_da_lista_volta_ao_padrao(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/perfis?per_page=999')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('roles.per_page', 15)
                ->where('filters.per_page', 15)
                ->where('roles.total', 19));
    }

    public function test_exportacao_csv_respeita_a_busca_por_nome(): void
    {
        $response = $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/perfis?formato=csv&search=administrador')
            ->assertOk();

        $conteudo = $response->streamedContent();

        $this->assertStringContainsString('administrador', $conteudo);
        $this->assertStringNotContainsString('cidadao', $conteudo);
    }

    public function test_cria_perfil_com_permissoes_granulares(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/perfis', [
                'name' => 'fiscal',
                'permissions' => ['acessar-gestao', 'consultar-cnaes'],
            ])
            ->assertRedirect();

        $role = Role::findByName('fiscal', 'web');

        $this->assertEqualsCanonicalizing(
            ['acessar-gestao', 'consultar-cnaes'],
            $role->permissions->pluck('name')->all(),
        );
    }

    public function test_permissoes_de_perfil_novo_tem_efeito_imediato(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'gestao')->post('/gestao/perfis', [
            'name' => 'fiscal',
            'permissions' => ['acessar-gestao', 'consultar-cnaes'],
        ]);

        $user = User::factory()->withAcceptedLgpdTerm()->create();
        $user->assignRole('fiscal');

        $this->actingAs($user, 'gestao')
            ->get('/gestao/cnaes')
            ->assertOk();

        $this->actingAs($user, 'gestao')
            ->post('/gestao/cnaes', [
                'code' => '0111-3/01',
                'description' => 'Cultivo de arroz',
                'section_code' => 'A',
                'section_description' => 'Agricultura, pecuária, produção florestal, pesca e aquicultura',
                'division_code' => '01',
                'division_description' => 'Agricultura, pecuária e serviços relacionados',
                'group_code' => '01.1',
                'group_description' => 'Produção de lavouras temporárias',
                'class_code' => '01.11-3',
                'class_description' => 'Cultivo de cereais',
            ])
            ->assertForbidden();
    }

    public function test_ajusta_permissoes_de_papel_estrutural(): void
    {
        $admin = $this->admin();
        $analista = Role::findByName('Analista Terceirizado', 'web');

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/perfis/{$analista->id}", [
                'name' => 'Analista Terceirizado',
                'permissions' => ['acessar-gestao', 'consultar-cnaes', 'consultar-acessos-de-qualquer-conta'],
            ])
            ->assertRedirect();

        $this->assertEqualsCanonicalizing(
            ['acessar-gestao', 'consultar-cnaes', 'consultar-acessos-de-qualquer-conta'],
            $analista->fresh()->permissions->pluck('name')->all(),
        );
    }

    public function test_nao_remove_acessar_gestao_do_administrador(): void
    {
        $admin = $this->admin();
        $administrador = Role::findByName('administrador', 'web');
        $before = $administrador->permissions->pluck('name')->all();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/perfis/{$administrador->id}", [
                'name' => 'administrador',
                'permissions' => ['manter-cnaes'],
            ])
            ->assertSessionHasErrors('permissions');

        $errors = session('errors')->get('permissions');
        $this->assertStringContainsString('acessar-gestao', implode(' ', $errors));

        $this->assertEqualsCanonicalizing(
            $before,
            $administrador->fresh()->permissions->pluck('name')->all(),
        );
    }

    public function test_papel_estrutural_nao_pode_ser_renomeado(): void
    {
        $admin = $this->admin();
        $cidadao = Role::findByName('cidadao', 'web');

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/perfis/{$cidadao->id}", [
                'name' => 'municipe',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame('cidadao', $cidadao->fresh()->name);
    }

    public function test_papel_estrutural_nao_pode_ser_excluido(): void
    {
        $admin = $this->admin();
        $administrador = Role::findByName('administrador', 'web');

        $this->actingAs($admin, 'gestao')
            ->delete("/gestao/perfis/{$administrador->id}")
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['name' => 'administrador', 'guard_name' => 'web']);
    }

    public function test_perfil_protegido_nao_pode_ser_renomeado_nem_excluido(): void
    {
        $admin = $this->admin();
        $protegido = Role::create(['name' => 'auditoria-externa', 'guard_name' => 'web', 'protegido' => true]);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/perfis/{$protegido->id}", ['name' => 'outro-nome'])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin, 'gestao')
            ->delete("/gestao/perfis/{$protegido->id}")
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['name' => 'auditoria-externa', 'protegido' => true]);
    }

    public function test_perfil_comum_pode_ser_renomeado(): void
    {
        $admin = $this->admin();
        $comum = Role::create(['name' => 'perfil-comum', 'guard_name' => 'web']);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/perfis/{$comum->id}", ['name' => 'perfil-renomeado', 'permissions' => []])
            ->assertRedirect();

        $this->assertSame('perfil-renomeado', $comum->fresh()->name);
    }

    public function test_listagem_marca_protegido_no_campo_structural(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/perfis?per_page=50')
            ->assertInertia(fn (Assert $page) => $page
                ->where('roles.data', fn ($roles) => collect($roles)->firstWhere('name', 'administrador')['structural'] === true
                    && collect($roles)->firstWhere('name', 'apoio')['structural'] === false));
    }

    public function test_exclusao_bloqueada_com_usuarios_vinculados(): void
    {
        $admin = $this->admin();
        $temporario = Role::create(['name' => 'temporario', 'guard_name' => 'web']);
        User::factory()->create()->assignRole('temporario');

        $this->actingAs($admin, 'gestao')
            ->delete("/gestao/perfis/{$temporario->id}")
            ->assertSessionHasErrors('role');

        $errors = session('errors')->get('role');
        $this->assertStringContainsString('usuários vinculados', implode(' ', $errors));

        $this->assertDatabaseHas('roles', ['name' => 'temporario', 'guard_name' => 'web']);
    }

    public function test_exclui_perfil_custom_sem_usuarios(): void
    {
        $admin = $this->admin();
        $descartavel = Role::create(['name' => 'descartavel', 'guard_name' => 'web']);

        $this->actingAs($admin, 'gestao')
            ->delete("/gestao/perfis/{$descartavel->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('roles', ['name' => 'descartavel', 'guard_name' => 'web']);
    }

    public function test_nome_duplicado_e_rejeitado(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/perfis', [
                'name' => 'cidadao',
                'permissions' => [],
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_operacoes_de_perfis_sao_auditadas(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'gestao')->post('/gestao/perfis', [
            'name' => 'fiscal',
            'permissions' => ['acessar-gestao'],
        ]);

        $fiscal = Role::findByName('fiscal', 'web');

        $this->actingAs($admin, 'gestao')->put("/gestao/perfis/{$fiscal->id}", [
            'name' => 'fiscal',
            'permissions' => ['acessar-gestao', 'consultar-cnaes'],
        ]);

        $this->actingAs($admin, 'gestao')->delete("/gestao/perfis/{$fiscal->id}");

        $events = Activity::where('log_name', 'perfis')->pluck('event');

        $this->assertContains('perfil-criado', $events);
        $this->assertContains('perfil-atualizado', $events);
        $this->assertContains('perfil-excluido', $events);

        $update = Activity::where('log_name', 'perfis')
            ->where('event', 'perfil-atualizado')
            ->first();

        $this->assertSame($admin->id, $update->causer_id);
        $this->assertEqualsCanonicalizing(['acessar-gestao'], $update->properties['permissoes_antes']);
        $this->assertEqualsCanonicalizing(
            ['acessar-gestao', 'consultar-cnaes'],
            $update->properties['permissoes_depois'],
        );
    }

    public function test_analista_nao_mantem_perfis(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/perfis')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
            'causer_id' => $analista->id,
        ]);
    }

    public function test_perfil_apontado_pelo_parametro_do_cidadao_nao_pode_ser_excluido_nem_renomeado(): void
    {
        $admin = $this->admin();
        $municipe = Role::create(['name' => 'municipe', 'guard_name' => 'web']);
        config(['viabiliza.portal.perfil_cidadao' => 'municipe']);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/perfis/{$municipe->id}", ['name' => 'outro'])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin, 'gestao')
            ->delete("/gestao/perfis/{$municipe->id}")
            ->assertSessionHasErrors('role');
    }
}
