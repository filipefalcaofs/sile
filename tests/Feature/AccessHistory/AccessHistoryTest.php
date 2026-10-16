<?php

namespace Tests\Feature\AccessHistory;

use App\Models\AccessLog;
use App\Models\Cidadao;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccessHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_usuario_ve_somente_os_proprios_acessos(): void
    {
        $user = Cidadao::factory()->withAcceptedLgpdTerm()->create();

        AccessLog::factory()->count(3)->create([
            'actor_type' => $user->getMorphClass(), 'actor_id' => $user->id,
            'email' => $user->email,
        ]);

        AccessLog::factory()->count(2)->create();

        $this->actingAs($user)
            ->get('/portal/acessos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/acessos')
                ->has('logs.data', 3));
    }

    public function test_bloqueio_sem_user_id_aparece_pelo_email(): void
    {
        $user = Cidadao::factory()->withAcceptedLgpdTerm()->create();

        AccessLog::factory()->create([
            'actor_type' => null, 'actor_id' => null,
            'email' => $user->email,
            'event' => 'bloqueio',
        ]);

        $this->actingAs($user)
            ->get('/portal/acessos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/acessos')
                ->has('logs.data', 1)
                ->where('logs.data.0.event', 'bloqueio'));
    }

    public function test_acessos_exibem_evento_data_e_ip(): void
    {
        $user = Cidadao::factory()->withAcceptedLgpdTerm()->create();

        AccessLog::factory()->create([
            'actor_type' => $user->getMorphClass(), 'actor_id' => $user->id,
            'email' => $user->email,
            'event' => 'falha',
            'ip_address' => '10.0.0.1',
        ]);

        $this->actingAs($user)
            ->get('/portal/acessos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/acessos')
                ->where('logs.data.0.event', 'falha')
                ->where('logs.data.0.ip_address', '10.0.0.1')
                ->has('logs.data.0.created_at'));
    }

    public function test_historico_e_paginado(): void
    {
        $user = Cidadao::factory()->withAcceptedLgpdTerm()->create();

        AccessLog::factory()->count(20)->create([
            'actor_type' => $user->getMorphClass(), 'actor_id' => $user->id,
            'email' => $user->email,
        ]);

        $this->actingAs($user)
            ->get('/portal/acessos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/acessos')
                ->has('logs.data', 15)
                ->has('logs.links')
                ->where('logs.total', 20));
    }

    public function test_admin_consulta_acessos_de_qualquer_conta(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $target = User::factory()->create();

        AccessLog::factory()->count(2)->create([
            'actor_type' => $target->getMorphClass(), 'actor_id' => $target->id,
            'email' => $target->email,
        ]);

        $this->actingAs($admin, 'gestao')
            ->get("/gestao/acessos/{$target->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/acessos')
                ->where('targetUser.name', $target->name)
                ->has('logs.data', 2));
    }

    public function test_consulta_administrativa_e_auditada(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $target = User::factory()->create();

        $this->actingAs($admin, 'gestao')
            ->get("/gestao/acessos/{$target->id}")
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'acessos',
            'event' => 'consulta-acessos',
            'result' => 'sucesso',
            'causer_id' => $admin->id,
        ]);
    }

    public function test_analista_sem_permissao_nao_consulta_terceiros(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $target = User::factory()->create();

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/acessos/{$target->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
            'causer_id' => $analista->id,
        ]);
    }

    public function test_usuario_inexistente_retorna_404(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/acessos/999999')
            ->assertNotFound();
    }

    public function test_visitante_nao_acessa_historico(): void
    {
        $this->get('/portal/acessos')->assertRedirect('/portal/login');
    }

    public function test_portal_nao_mostra_acesso_de_servidor_com_o_mesmo_id(): void
    {
        $servidor = User::factory()->create();
        $cidadao = Cidadao::factory()->withAcceptedLgpdTerm()->create(['id' => $servidor->id]);

        AccessLog::factory()->create([
            'actor_type' => User::class, 'actor_id' => $servidor->id,
            'email' => 'servidor@example.test', 'ip_address' => '10.8.8.8',
        ]);
        AccessLog::factory()->create([
            'actor_type' => Cidadao::class, 'actor_id' => $cidadao->id,
            'email' => $cidadao->email, 'ip_address' => '10.1.1.1',
        ]);

        $this->actingAs($cidadao)
            ->get('/portal/acessos')
            ->assertInertia(fn (Assert $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.ip_address', '10.1.1.1'));
    }

    public function test_portal_nao_mostra_acesso_de_servidor_com_o_mesmo_email(): void
    {
        $cidadao = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $servidor = User::factory()->create(['email' => $cidadao->email]);

        AccessLog::factory()->create([
            'actor_type' => User::class, 'actor_id' => $servidor->id,
            'email' => $servidor->email, 'ip_address' => '10.8.8.8',
        ]);

        $this->actingAs($cidadao)
            ->get('/portal/acessos')
            ->assertInertia(fn (Assert $page) => $page->has('logs.data', 0));
    }

    public function test_falha_pre_login_sem_conta_aparece_pelo_email_do_cidadao(): void
    {
        $cidadao = Cidadao::factory()->withAcceptedLgpdTerm()->create();

        AccessLog::factory()->create([
            'actor_type' => null, 'actor_id' => null,
            'email' => $cidadao->email, 'event' => 'falha',
        ]);

        $this->actingAs($cidadao)
            ->get('/portal/acessos')
            ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1));
    }

    public function test_gestao_nao_mostra_acesso_de_cidadao_com_mesmo_id_ou_email(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $servidor = User::factory()->create();
        $cidadao = Cidadao::factory()->create(['id' => $servidor->id, 'email' => $servidor->email]);

        AccessLog::factory()->create([
            'actor_type' => Cidadao::class, 'actor_id' => $cidadao->id,
            'email' => $cidadao->email, 'ip_address' => '10.9.9.9',
        ]);
        AccessLog::factory()->create([
            'actor_type' => User::class, 'actor_id' => $servidor->id,
            'email' => $servidor->email, 'ip_address' => '10.2.2.2',
        ]);

        $this->actingAs($admin, 'gestao')
            ->get("/gestao/acessos/{$servidor->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.ip_address', '10.2.2.2'));

        $csv = $this->actingAs($admin, 'gestao')
            ->get("/gestao/acessos/{$servidor->id}?formato=csv")
            ->streamedContent();

        $this->assertStringContainsString('10.2.2.2', $csv);
        $this->assertStringNotContainsString('10.9.9.9', $csv);
    }

    public function test_falha_sem_conta_de_um_ambiente_nao_aparece_no_outro(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $servidor = User::factory()->create(['email' => 'mesma@pessoa.test']);
        $cidadao = Cidadao::factory()->withAcceptedLgpdTerm()->create(['email' => 'mesma@pessoa.test']);

        AccessLog::factory()->create([
            'actor_type' => null, 'actor_id' => null, 'email' => 'mesma@pessoa.test',
            'event' => 'falha', 'channel' => 'gestao', 'ip_address' => '10.1.1.1',
        ]);
        AccessLog::factory()->create([
            'actor_type' => null, 'actor_id' => null, 'email' => 'mesma@pessoa.test',
            'event' => 'falha', 'channel' => 'portal', 'ip_address' => '10.3.3.3',
        ]);

        $this->actingAs($cidadao)
            ->get('/portal/acessos')
            ->assertInertia(fn (Assert $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.ip_address', '10.3.3.3'));

        $this->actingAs($admin, 'gestao')
            ->get("/gestao/acessos/{$servidor->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.ip_address', '10.1.1.1'));

        $csv = $this->actingAs($admin, 'gestao')
            ->get("/gestao/acessos/{$servidor->id}?formato=csv")
            ->streamedContent();

        $this->assertStringContainsString('10.1.1.1', $csv);
        $this->assertStringNotContainsString('10.3.3.3', $csv);
    }
}
