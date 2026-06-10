<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_cria_papeis_e_permissoes_da_fase(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['cidadao', 'analista', 'gestor', 'administrador'] as $role) {
            $this->assertSame($role, Role::findByName($role, 'web')->name);
        }

        $permissions = [
            'acessar-gestao',
            'consultar-acessos-de-qualquer-conta',
            'gerenciar-procuracoes-proprias',
        ];

        foreach ($permissions as $permission) {
            $this->assertSame($permission, Permission::findByName($permission, 'web')->name);
        }
    }

    public function test_atribuicoes_de_permissao_por_papel(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $administrador = Role::findByName('administrador', 'web');
        $this->assertTrue($administrador->hasPermissionTo('acessar-gestao'));
        $this->assertTrue($administrador->hasPermissionTo('consultar-acessos-de-qualquer-conta'));

        $this->assertTrue(Role::findByName('analista', 'web')->hasPermissionTo('acessar-gestao'));
        $this->assertTrue(Role::findByName('gestor', 'web')->hasPermissionTo('acessar-gestao'));

        $cidadao = Role::findByName('cidadao', 'web');
        $this->assertTrue($cidadao->hasPermissionTo('gerenciar-procuracoes-proprias'));
        $this->assertFalse($cidadao->hasPermissionTo('acessar-gestao'));
    }

    public function test_estados_da_factory_atribuem_papel(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue(User::factory()->cidadao()->create()->hasRole('cidadao'));
        $this->assertTrue(User::factory()->analista()->create()->hasRole('analista'));
        $this->assertTrue(User::factory()->gestor()->create()->hasRole('gestor'));
        $this->assertTrue(User::factory()->administrador()->create()->hasRole('administrador'));
    }

    public function test_seeder_e_idempotente(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(4, Role::query()->count());
        $this->assertSame(3, Permission::query()->count());
    }
}
