<?php

namespace Tests\Feature\Users;

use App\Models\Activity;
use App\Models\Cidadao;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageUsersTest extends TestCase
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

    public function test_aba_padrao_lista_equipe_sedur_com_papeis_e_situacao(): void
    {
        $admin = $this->admin();

        User::factory()->analista()->inactive()->create();
        Cidadao::factory()->create();

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

    public function test_aba_portal_lista_apenas_cidadaos(): void
    {
        $admin = $this->admin();

        User::factory()->analista()->create();
        $cidada = Cidadao::factory()->create(['name' => 'Maria Souza']);
        $semSenha = Cidadao::factory()->semSenha()->create(['name' => 'Sem Senha']);

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/usuarios?tab=portal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.tab', 'portal')
                ->has('users.data', 2)
                ->where('users.data', fn ($data) => collect($data)->pluck('name')->sort()->values()->all() === collect([$cidada->name, $semSenha->name])->sort()->values()->all()));
    }

    public function test_conta_ad_sem_papel_conta_como_equipe_sedur_e_nunca_como_portal(): void
    {
        $admin = $this->admin();
        User::factory()->ad('servidora.ad')->create(['name' => 'Servidora AD']);
        Cidadao::factory()->create(['name' => 'Cidadã Portal']);

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/usuarios')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.gestao', 2)
                ->where('counts.portal', 1)
                ->where('users.data', fn ($data) => collect($data)->pluck('name')->contains('Servidora AD')));

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/usuarios?tab=portal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.data', fn ($data) => ! collect($data)->pluck('name')->contains('Servidora AD')));
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

    public function test_servidores_sem_acesso_a_gestao_aparecem_na_aba_gestao_com_indicador(): void
    {
        $admin = $this->admin();
        $fiscal = User::factory()->create(['name' => 'Fiscal Herdado']);
        $fiscal->assignRole('Fiscal');
        $direto = User::factory()->create(['name' => 'Permissao Direta']);
        $direto->givePermissionTo('acessar-gestao');
        $semPerfil = User::factory()->create(['name' => 'Sem Perfil']);
        $analista = User::factory()->analista()->create(['name' => 'Analista']);
        Cidadao::factory()->create(['name' => 'Cidada Portal']);

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/usuarios')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 5)
                ->where('counts.gestao', 5)
                ->where('counts.portal', 1)
                ->where('users.data', fn ($data) => collect($data)->pluck('sem_acesso_gestao', 'name')->sortKeys()->all() === collect([
                    $admin->name => false,
                    'Analista' => false,
                    'Fiscal Herdado' => true,
                    'Permissao Direta' => false,
                    'Sem Perfil' => true,
                ])->sortKeys()->all()));

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/usuarios?search=fiscal')
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.sem_acesso_gestao', true));

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/usuarios/{$fiscal->id}/inativacao")
            ->assertRedirect();
        $this->assertNotNull($fiscal->fresh()->inactivated_at);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/usuarios/{$fiscal->id}/papel", ['role' => 'Coordenador'])
            ->assertRedirect();
        $this->assertTrue($fiscal->fresh()->hasRole('Coordenador'));
        $this->assertSame([], $semPerfil->fresh()->getRoleNames()->all());
        $this->assertTrue($analista->fresh()->hasRole('Analista Terceirizado'));
    }

    public function test_conta_ad_sem_perfil_nao_leva_o_indicador(): void
    {
        $admin = $this->admin();
        User::factory()->ad('servidor.ad')->create(['name' => 'Servidor AD']);

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/usuarios?search=Servidor AD')
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.sem_acesso_gestao', false));
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

        Cidadao::factory()->create(['name' => 'Maria Souza', 'email' => 'maria@x.dev']);
        Cidadao::factory()->create(['name' => 'João Lima', 'email' => 'joao@x.dev']);

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
        $user = Cidadao::factory()->create();

        $this->actingAs($admin, 'gestao')
            ->put(route('gestao.cidadaos.inativacao.update', $user))
            ->assertRedirect();

        $this->assertNotNull($user->fresh()->inactivated_at);

        $activity = Activity::where('log_name', 'usuarios')
            ->where('event', 'cidadao-inativado')
            ->first();

        $this->assertNotNull($activity, 'Esperava activity de inativação do usuário');
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame($user->id, $activity->properties['target_cidadao_id']);
    }

    public function test_reativa_usuario_com_auditoria(): void
    {
        $admin = $this->admin();
        $user = Cidadao::factory()->inactive()->create();

        $this->actingAs($admin, 'gestao')
            ->put(route('gestao.cidadaos.inativacao.update', $user))
            ->assertRedirect();

        $this->assertNull($user->fresh()->inactivated_at);

        $activity = Activity::where('log_name', 'usuarios')
            ->where('event', 'cidadao-reativado')
            ->first();

        $this->assertNotNull($activity, 'Esperava activity de reativação do usuário');
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame($user->id, $activity->properties['target_cidadao_id']);
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
            ->put("/gestao/usuarios/{$user->id}/papel", ['role' => 'Coordenador'])
            ->assertRedirect();

        $user = $user->fresh();

        $this->assertTrue($user->hasRole('Coordenador'));
        $this->assertFalse($user->hasRole('Analista Terceirizado'));

        $activity = Activity::where('log_name', 'usuarios')
            ->where('event', 'papel-alterado')
            ->first();

        $this->assertNotNull($activity, 'Esperava activity de alteração de papel');
        $this->assertSame(['Analista Terceirizado'], $activity->properties['papel_anterior']);
        $this->assertSame('Coordenador', $activity->properties['papel_novo']);
    }

    public function test_papel_inexistente_e_rejeitado(): void
    {
        $admin = $this->admin();
        $user = User::factory()->analista()->create();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/usuarios/{$user->id}/papel", ['role' => 'papel-fantasma'])
            ->assertSessionHasErrors('role');

        $this->assertTrue($user->fresh()->hasRole('Analista Terceirizado'));
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
        $user = Cidadao::factory()->create();

        $this->actingAs($admin, 'gestao')
            ->put(route('gestao.cidadaos.inativacao.update', $user))
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
