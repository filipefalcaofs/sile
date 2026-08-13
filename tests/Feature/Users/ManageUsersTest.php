<?php

namespace Tests\Feature\Users;

use App\Models\Activity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
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

    public function test_aba_padrao_lista_equipe_sedur_com_papeis_e_situacao(): void
    {
        $admin = $this->admin();

        User::factory()->analista()->inactive()->create();
        User::factory()->cidadao()->create();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/usuarios')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/usuarios/index')
                ->where('filters.tab', 'gestao')
                ->has('users.data', 2)
                ->has('users.data.0', fn (Assert $item) => $item
                    ->hasAll(['id', 'name', 'email', 'roles', 'inactivated_at', 'cpf_masked'])
                    ->missing('cpf')
                    ->etc())
                ->where('users.data.0.cpf_masked', fn ($masked) => preg_match('/^\*{3}\.\*{3}\.\*{3}-\d{2}$/', (string) $masked) === 1)
                ->where('counts.gestao', 2)
                ->where('counts.portal', 1));
    }

    public function test_aba_portal_lista_apenas_usuarios_sem_acesso_a_gestao(): void
    {
        $admin = $this->admin();

        User::factory()->analista()->create();
        $cidada = User::factory()->cidadao()->create(['name' => 'Maria Souza']);
        $semPapel = User::factory()->create(['name' => 'Sem Papel']);

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/usuarios?tab=portal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.tab', 'portal')
                ->has('users.data', 2)
                ->where('users.data', fn ($data) => collect($data)->pluck('name')->sort()->values()->all() === collect([$cidada->name, $semPapel->name])->sort()->values()->all()));
    }

    public function test_perfil_customizado_com_acesso_a_gestao_conta_como_equipe(): void
    {
        $admin = $this->admin();

        $fiscal = Role::create(['name' => 'fiscal']);
        $fiscal->givePermissionTo('acessar-gestao');

        $user = User::factory()->create(['name' => 'Fiscal Custom']);
        $user->assignRole('fiscal');

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/usuarios')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.tab', 'gestao')
                ->where('users.data', fn ($data) => collect($data)->pluck('name')->contains('Fiscal Custom')));
    }

    public function test_aba_invalida_cai_na_equipe_sedur(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/usuarios?tab=banana')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('filters.tab', 'gestao'));
    }

    public function test_busca_filtra_por_nome_ou_email_dentro_da_aba(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create([
            'name' => 'Admin SEDUR',
            'email' => 'admin@sedur.test',
        ]);

        User::factory()->cidadao()->create(['name' => 'Maria Souza', 'email' => 'maria@x.dev']);
        User::factory()->cidadao()->create(['name' => 'João Lima', 'email' => 'joao@x.dev']);

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/usuarios?tab=portal&search=maria')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Maria Souza'));

        // A mesma busca na aba da equipe não encontra a cidadã.
        $this->actingAs($admin, 'gestao')
            ->get('/gestao/usuarios?search=maria')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 0));
    }

    public function test_inativa_usuario_com_auditoria(): void
    {
        $admin = $this->admin();
        $user = User::factory()->cidadao()->create();

        $this->actingAs($admin, 'gestao')
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

        $this->actingAs($admin, 'gestao')
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

        $this->actingAs($admin, 'gestao')
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

        $this->actingAs($admin, 'gestao')
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

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/usuarios/{$user->id}/papel", ['role' => 'papel-fantasma'])
            ->assertSessionHasErrors('role');

        $this->assertTrue($user->fresh()->hasRole('analista'));
    }

    public function test_gestor_sem_permissao_nao_gerencia_usuarios(): void
    {
        $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();

        $this->actingAs($gestor, 'gestao')
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

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/usuarios/{$user->id}/inativacao")
            ->assertRedirect();

        $this->post(route('gestao.logout'));
        $this->assertGuest('gestao');

        $response = $this->post('/portal/login', [
            'cpf' => $user->cpf,
            'password' => 'password',
        ]);

        $this->assertGuest('web');
        $response->assertSessionHasErrors('cpf');

        $errors = session('errors')->get('cpf');
        $this->assertStringContainsString('inativa', implode(' ', $errors));
    }
}
