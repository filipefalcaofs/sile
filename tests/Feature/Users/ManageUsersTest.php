<?php

namespace Tests\Feature\Users;

use App\Models\Activity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManageUsersTest extends TestCase
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

    public function test_administrador_lista_usuarios_com_papeis_e_situacao(): void
    {
        $admin = $this->admin();

        User::factory()->analista()->create();
        User::factory()->cidadao()->inactive()->create();

        $this->actingAs($admin)
            ->get('/gestao/usuarios')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/usuarios/index')
                ->has('users.data', 3)
                ->has('users.data.0', fn (Assert $item) => $item
                    ->hasAll(['id', 'name', 'email', 'roles', 'inactivated_at', 'cpf_masked'])
                    ->missing('cpf')
                    ->etc())
                ->where('users.data.0.cpf_masked', fn ($masked) => preg_match('/^\*{3}\.\*{3}\.\*{3}-\d{2}$/', (string) $masked) === 1));
    }

    public function test_busca_filtra_por_nome_ou_email(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create([
            'name' => 'Admin SEDUR',
            'email' => 'admin@sedur.test',
        ]);

        User::factory()->cidadao()->create(['name' => 'Maria Souza', 'email' => 'maria@x.dev']);
        User::factory()->cidadao()->create(['name' => 'João Lima', 'email' => 'joao@x.dev']);

        $this->actingAs($admin)
            ->get('/gestao/usuarios?search=maria')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Maria Souza'));
    }

    public function test_inativa_usuario_com_auditoria(): void
    {
        $admin = $this->admin();
        $user = User::factory()->cidadao()->create();

        $this->actingAs($admin)
            ->put("/gestao/usuarios/{$user->id}/inativacao")
            ->assertRedirect();

        $this->assertNotNull($user->fresh()->inactivated_at);

        $activity = Activity::where('log_name', 'usuarios')
            ->where('event', 'usuario-inativado')
            ->first();

        $this->assertNotNull($activity, 'Esperava activity de inativação do usuário');
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame($user->id, $activity->properties['target_user_id']);
    }

    public function test_reativa_usuario_com_auditoria(): void
    {
        $admin = $this->admin();
        $user = User::factory()->cidadao()->inactive()->create();

        $this->actingAs($admin)
            ->put("/gestao/usuarios/{$user->id}/inativacao")
            ->assertRedirect();

        $this->assertNull($user->fresh()->inactivated_at);

        $activity = Activity::where('log_name', 'usuarios')
            ->where('event', 'usuario-reativado')
            ->first();

        $this->assertNotNull($activity, 'Esperava activity de reativação do usuário');
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame($user->id, $activity->properties['target_user_id']);
    }

    public function test_administrador_nao_inativa_a_propria_conta(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put("/gestao/usuarios/{$admin->id}/inativacao")
            ->assertSessionHasErrors([
                'user' => 'Você não pode inativar a própria conta.',
            ]);

        $this->assertNull($admin->fresh()->inactivated_at);
    }

    public function test_altera_papel_do_usuario_com_auditoria(): void
    {
        $admin = $this->admin();
        $user = User::factory()->analista()->create();

        $this->actingAs($admin)
            ->put("/gestao/usuarios/{$user->id}/papel", ['role' => 'gestor'])
            ->assertRedirect();

        $user = $user->fresh();

        $this->assertTrue($user->hasRole('gestor'));
        $this->assertFalse($user->hasRole('analista'));

        $activity = Activity::where('log_name', 'usuarios')
            ->where('event', 'papel-alterado')
            ->first();

        $this->assertNotNull($activity, 'Esperava activity de alteração de papel');
        $this->assertSame(['analista'], $activity->properties['papel_anterior']);
        $this->assertSame('gestor', $activity->properties['papel_novo']);
    }

    public function test_papel_inexistente_e_rejeitado(): void
    {
        $admin = $this->admin();
        $user = User::factory()->analista()->create();

        $this->actingAs($admin)
            ->put("/gestao/usuarios/{$user->id}/papel", ['role' => 'papel-fantasma'])
            ->assertSessionHasErrors('role');

        $this->assertTrue($user->fresh()->hasRole('analista'));
    }

    public function test_gestor_sem_permissao_nao_gerencia_usuarios(): void
    {
        $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();

        $this->actingAs($gestor)
            ->get('/gestao/usuarios')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
            'causer_id' => $gestor->id,
        ]);
    }

    public function test_inativacao_pela_interface_bloqueia_o_login_de_verdade(): void
    {
        $admin = $this->admin();
        $user = User::factory()->cidadao()->create();

        $this->actingAs($admin)
            ->put("/gestao/usuarios/{$user->id}/inativacao")
            ->assertRedirect();

        $this->post(route('logout'));
        $this->assertGuest();

        $response = $this->post('/portal/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');

        $errors = session('errors')->get('email');
        $this->assertStringContainsString('inativa', implode(' ', $errors));
    }
}
