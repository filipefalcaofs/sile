<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Login híbrido do console: contas AD validam na API institucional SEDUR,
 * contas locais no hash local. Sessão resultante é sempre Laravel (guard
 * gestao); a senha AD e o token Bearer são transitórios.
 */
class GestaoLoginAdTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        config(['sile.integrations.sedur_auth.base_url' => 'https://sedur.test/k8s/prd']);

        // Settings e RateLimiter usam cache de processo; cada teste começa limpo
        // para o interruptor features.ad_login e o throttle por IP valerem.
        Cache::flush();
    }

    private function fakeAdSucesso(string $nome = 'Nome Sincronizado'): void
    {
        Http::fake([
            '*/ws-autenticacao/auth' => Http::response(['token' => 'tk-fake']),
            '*/pessoaView/buscarPorCPF/*' => Http::response([
                'nome' => $nome,
                'endereco' => 'Rua Exemplo, 1',
                'telefone' => '71999990000',
            ]),
        ]);
    }

    public function test_usuario_ad_autentica_pela_api_e_sincroniza_o_nome(): void
    {
        $this->fakeAdSucesso();

        $servidor = User::factory()->administrador()->ad('filipefalcao')->create([
            'name' => 'Nome Antigo',
        ]);

        $response = $this->post('/gestao/login', [
            'email' => $servidor->email,
            'password' => 'senha-corporativa',
        ]);

        $response->assertRedirect('/gestao');
        $this->assertAuthenticatedAs($servidor, 'gestao');

        Http::assertSent(fn ($request) => $request->url() === 'https://sedur.test/k8s/prd/ws-autenticacao/auth'
            && $request['usuario'] === 'filipefalcao'
            && $request['senha'] === 'senha-corporativa');

        $this->assertSame('Nome Sincronizado', $servidor->fresh()->name);
        $this->assertSame($servidor->phone, $servidor->fresh()->phone);

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $servidor->id,
            'event' => 'login',
            'channel' => 'gestao',
            'origin' => 'ad',
        ]);
    }

    public function test_credencial_ad_invalida_tem_mensagem_identica_a_de_senha_local(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response([], 401)]);

        $servidorAd = User::factory()->administrador()->ad('filipefalcao')->create();
        $servidorLocal = User::factory()->administrador()->create();

        $respostaAd = $this->post('/gestao/login', [
            'email' => $servidorAd->email,
            'password' => 'senha-errada',
        ]);

        $respostaLocal = $this->post('/gestao/login', [
            'email' => $servidorLocal->email,
            'password' => 'senha-errada',
        ]);

        $respostaAd->assertSessionHasErrors(['email' => __('auth.failed')]);
        $respostaLocal->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest('gestao');

        $this->assertDatabaseHas('access_logs', [
            'email' => $servidorAd->email,
            'event' => 'falha',
            'origin' => 'ad',
        ]);
    }

    public function test_api_ad_indisponivel_nao_cai_para_a_senha_local(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response('erro', 500)]);

        $servidor = User::factory()->administrador()->ad('filipefalcao')->create();

        $response = $this->post('/gestao/login', [
            'email' => $servidor->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Autenticação institucional indisponível no momento. Tente novamente em instantes.',
        ]);

        $this->assertGuest('gestao');

        $this->assertDatabaseHas('access_logs', [
            'email' => $servidor->email,
            'event' => 'ad-indisponivel',
            'channel' => 'gestao',
        ]);
    }

    public function test_integracao_ad_desligada_bloqueia_com_aviso_e_auditoria(): void
    {
        config(['sile.features.ad_login' => false]);
        Http::fake();

        $servidor = User::factory()->administrador()->ad('filipefalcao')->create();

        $response = $this->post('/gestao/login', [
            'email' => $servidor->email,
            'password' => 'senha-corporativa',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'A autenticação corporativa está desabilitada. Procure o administrador do sistema.',
        ]);

        $this->assertGuest('gestao');
        Http::assertNothingSent();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
        ]);
    }

    public function test_usuario_local_nao_chama_a_api_ad(): void
    {
        Http::fake();

        $servidor = User::factory()->administrador()->create();

        $this->post('/gestao/login', [
            'email' => $servidor->email,
            'password' => 'password',
        ])->assertRedirect('/gestao');

        Http::assertNothingSent();

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $servidor->id,
            'event' => 'login',
            'origin' => 'local',
        ]);
    }

    public function test_throttle_bloqueia_antes_de_chamar_a_api_ad(): void
    {
        config(['sile.security.login.max_attempts' => 3]);
        Http::fake(['*/ws-autenticacao/auth' => Http::response([], 401)]);

        $servidor = User::factory()->administrador()->ad('filipefalcao')->create();

        foreach (range(1, 3) as $tentativa) {
            $this->post('/gestao/login', [
                'email' => $servidor->email,
                'password' => 'senha-errada',
            ]);
        }

        $this->post('/gestao/login', [
            'email' => $servidor->email,
            'password' => 'senha-errada',
        ])->assertSessionHasErrors('email');

        Http::assertSentCount(3);
    }

    public function test_usuario_ad_sem_permissao_no_console_e_negado_e_auditado(): void
    {
        $this->fakeAdSucesso();

        $cidadao = User::factory()->cidadao()->ad('filipefalcao')->create();

        $this->post('/gestao/login', [
            'email' => $cidadao->email,
            'password' => 'senha-corporativa',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('gestao');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_usuario_ad_inativo_e_negado(): void
    {
        $this->fakeAdSucesso();

        $servidor = User::factory()->administrador()->ad('filipefalcao')->inactive()->create();

        $this->post('/gestao/login', [
            'email' => $servidor->email,
            'password' => 'senha-corporativa',
        ])->assertSessionHasErrors([
            'email' => 'Sua conta está inativa. Procure o administrador do sistema.',
        ]);

        $this->assertGuest('gestao');
    }

    public function test_falha_na_sincronizacao_nao_derruba_o_login_e_fica_auditada(): void
    {
        Http::fake([
            '*/ws-autenticacao/auth' => Http::response(['token' => 'tk-fake']),
            '*/pessoaView/buscarPorCPF/*' => Http::response([], 502),
        ]);

        $servidor = User::factory()->administrador()->ad('filipefalcao')->create();

        $this->post('/gestao/login', [
            'email' => $servidor->email,
            'password' => 'senha-corporativa',
        ])->assertRedirect('/gestao');

        $this->assertAuthenticatedAs($servidor, 'gestao');

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $servidor->id,
            'event' => 'ad-sincronizacao-falhou',
            'channel' => 'gestao',
        ]);
    }

    public function test_senha_ad_nao_aparece_em_nenhum_registro_de_acesso(): void
    {
        $this->fakeAdSucesso();

        $servidor = User::factory()->administrador()->ad('filipefalcao')->create();

        $this->post('/gestao/login', [
            'email' => $servidor->email,
            'password' => 'SuperSecreta123',
        ]);

        foreach (DB::table('access_logs')->get() as $log) {
            $this->assertStringNotContainsString('SuperSecreta123', (string) json_encode($log));
        }
    }
}
