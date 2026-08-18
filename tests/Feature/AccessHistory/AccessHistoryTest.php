<?php

namespace Tests\Feature\AccessHistory;

use App\Models\AccessLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccessHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_usuario_ve_somente_os_proprios_acessos(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        AccessLog::factory()->count(3)->create([
            'user_id' => $user->id,
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
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        AccessLog::factory()->create([
            'user_id' => null,
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
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        AccessLog::factory()->create([
            'user_id' => $user->id,
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
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        AccessLog::factory()->count(20)->create([
            'user_id' => $user->id,
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
            'user_id' => $target->id,
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
}
