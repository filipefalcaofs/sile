<?php

namespace Tests\Feature\Roles;

use App\Models\Activity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageRolesTest extends TestCase
{
    use RefreshDatabase;

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
            ->get('/gestao/perfis')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/perfis/index')
                ->has('roles', 4)
                ->has('roles.0', fn (Assert $item) => $item
                    ->hasAll(['id', 'name', 'permissions', 'users_count', 'structural']))
                ->where('roles.0.name', 'administrador')
                ->where('roles.0.structural', true)
                ->where('roles.0.users_count', 1)
                ->has('permissions', 9));
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
        $analista = Role::findByName('analista', 'web');

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/perfis/{$analista->id}", [
                'name' => 'analista',
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
}
