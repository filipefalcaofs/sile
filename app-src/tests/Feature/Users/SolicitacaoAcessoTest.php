<?php

namespace Tests\Feature\Users;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SolicitacaoAcessoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function servidorAd(): User
    {
        $user = User::factory()->ad('servidora.ad')->withAcceptedLgpdTerm()->create(['name' => 'Servidora AD']);
        $user->givePermissionTo('acessar-gestao');

        return $user;
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    public function test_painel_do_servidor_sem_papel_lista_os_perfis_solicitaveis(): void
    {
        $this->actingAs($this->servidorAd(), 'gestao')
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/dashboard')
                ->where('acesso.solicitacao', null)
                ->where('acesso.perfis', fn ($perfis) => collect($perfis)->contains('Coordenador')
                    && collect($perfis)->contains('Chefe de setor')
                    && ! collect($perfis)->contains('administrador')
                    && ! collect($perfis)->contains('cidadao')
                    && ! collect($perfis)->contains('Diretor')));
    }

    public function test_quem_ja_tem_papel_nao_recebe_o_bloco_de_acesso(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('acesso', null));
    }

    public function test_servidor_solicita_um_perfil(): void
    {
        $servidor = $this->servidorAd();

        $this->actingAs($servidor, 'gestao')
            ->post('/gestao/solicitacao-acesso', [
                'role' => 'Coordenador',
                'justificativa' => 'Coordeno a análise do setor de viabilidade.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('access_requests', [
            'user_id' => $servidor->id,
            'status' => AccessRequestStatus::Pendente->value,
        ]);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'usuarios', 'event' => 'solicitacao-acesso-criada']);
    }

    public function test_segunda_solicitacao_pendente_e_barrada(): void
    {
        $servidor = $this->servidorAd();
        AccessRequest::factory()->create(['user_id' => $servidor->id]);

        $this->actingAs($servidor, 'gestao')
            ->post('/gestao/solicitacao-acesso', [
                'role' => 'Coordenador',
                'justificativa' => 'Coordeno a análise do setor de viabilidade.',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('access_requests', 1);
    }

    public function test_perfil_fora_da_lista_e_justificativa_curta_sao_rejeitados(): void
    {
        $servidor = $this->servidorAd();

        foreach (['administrador', 'cidadao', 'Diretor'] as $perfil) {
            $this->actingAs($servidor, 'gestao')
                ->post('/gestao/solicitacao-acesso', ['role' => $perfil, 'justificativa' => 'Preciso acessar o console.'])
                ->assertSessionHasErrors('role');
        }

        $this->actingAs($servidor, 'gestao')
            ->post('/gestao/solicitacao-acesso', ['role' => 'Coordenador', 'justificativa' => 'curta'])
            ->assertSessionHasErrors('justificativa');

        $this->assertDatabaseCount('access_requests', 0);
    }

    public function test_quem_ja_tem_papel_nao_cria_solicitacao(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->post('/gestao/solicitacao-acesso', [
                'role' => 'Coordenador',
                'justificativa' => 'Coordeno a análise do setor de viabilidade.',
            ])
            ->assertRedirect(route('gestao.dashboard'));

        $this->assertDatabaseCount('access_requests', 0);
    }

    public function test_painel_mostra_a_recusa_e_permite_pedir_de_novo(): void
    {
        $servidor = $this->servidorAd();
        AccessRequest::factory()->recusada('Procure sua chefia.')->create(['user_id' => $servidor->id]);

        $this->actingAs($servidor, 'gestao')
            ->get('/gestao')
            ->assertInertia(fn (Assert $page) => $page
                ->where('acesso.solicitacao.status', 'recusada')
                ->where('acesso.solicitacao.motivo', 'Procure sua chefia.'));

        $this->actingAs($servidor, 'gestao')
            ->post('/gestao/solicitacao-acesso', [
                'role' => 'Subcoordenador',
                'justificativa' => 'Atuo como subcoordenadora da análise.',
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseCount('access_requests', 2);
    }

    public function test_administrador_ve_as_solicitacoes_pendentes_em_usuarios(): void
    {
        $solicitacao = AccessRequest::factory()->create(['justificativa' => 'Coordeno a análise do setor.']);
        AccessRequest::factory()->aprovada()->create();

        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/usuarios')
            ->assertInertia(fn (Assert $page) => $page
                ->has('solicitacoes', 1)
                ->where('solicitacoes.0.id', $solicitacao->id)
                ->where('solicitacoes.0.perfil', 'Coordenador')
                ->where('solicitacoes.0.justificativa', 'Coordeno a análise do setor.'));
    }

    public function test_administrador_aprova_com_outro_perfil(): void
    {
        $admin = $this->admin();
        $solicitacao = AccessRequest::factory()->create();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/usuarios/solicitacoes/{$solicitacao->id}/aprovar", ['role' => 'Subcoordenador'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $user = $solicitacao->user->fresh();
        $this->assertSame(['Subcoordenador'], $user->getRoleNames()->all());

        $solicitacao->refresh();
        $this->assertSame(AccessRequestStatus::Aprovada, $solicitacao->status);
        $this->assertSame($admin->id, $solicitacao->decided_by_user_id);
        $this->assertNotNull($solicitacao->decided_at);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'usuarios', 'event' => 'solicitacao-acesso-aprovada']);
    }

    public function test_administrador_recusa_com_motivo_obrigatorio(): void
    {
        $admin = $this->admin();
        $solicitacao = AccessRequest::factory()->create();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/usuarios/solicitacoes/{$solicitacao->id}/recusar", [])
            ->assertSessionHasErrors('motivo');

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/usuarios/solicitacoes/{$solicitacao->id}/recusar", ['motivo' => 'Perfil não compatível com a lotação.'])
            ->assertSessionHas('status');

        $solicitacao->refresh();
        $this->assertSame(AccessRequestStatus::Recusada, $solicitacao->status);
        $this->assertSame('Perfil não compatível com a lotação.', $solicitacao->decision_note);
        $this->assertSame([], $solicitacao->user->fresh()->getRoleNames()->all());
        $this->assertDatabaseHas('activity_log', ['log_name' => 'usuarios', 'event' => 'solicitacao-acesso-recusada']);
    }

    public function test_solicitacao_decidida_nao_e_decidida_de_novo(): void
    {
        $admin = $this->admin();
        $solicitacao = AccessRequest::factory()->recusada()->create();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/usuarios/solicitacoes/{$solicitacao->id}/aprovar", ['role' => 'Coordenador'])
            ->assertSessionHas('error');

        $this->assertSame([], $solicitacao->user->fresh()->getRoleNames()->all());
    }

    public function test_quem_nao_gerencia_usuarios_recebe_403(): void
    {
        $solicitacao = AccessRequest::factory()->create();
        $coordenador = User::factory()->gestor()->withAcceptedLgpdTerm()->create();

        $this->actingAs($coordenador, 'gestao')
            ->put("/gestao/usuarios/solicitacoes/{$solicitacao->id}/aprovar", ['role' => 'Coordenador'])
            ->assertForbidden();

        $this->actingAs($coordenador, 'gestao')
            ->put("/gestao/usuarios/solicitacoes/{$solicitacao->id}/recusar", ['motivo' => 'Teste de permissão.'])
            ->assertForbidden();
    }
}
