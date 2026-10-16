<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\User;
use App\Notifications\ResetPasswordQueued;
use App\Notifications\VerifyEmailQueued;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AutenticacaoCidadaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function dadosCadastro(array $sobrescrever = []): array
    {
        return array_merge([
            'name' => 'Maria da Silva',
            'email' => 'maria@example.com',
            'cpf' => '52998224725',
            'phone' => '(71) 99999-1234',
            'password' => 'Senha1234',
            'password_confirmation' => 'Senha1234',
        ], $sobrescrever);
    }

    public function test_servidor_com_o_mesmo_cpf_e_email_consegue_criar_conta_de_cidadao(): void
    {
        User::factory()->create(['cpf' => '52998224725', 'email' => 'maria@example.com']);

        $this->post('/portal/register', $this->dadosCadastro())->assertRedirect();

        $this->assertDatabaseHas('cidadaos', ['cpf' => '52998224725']);
        $this->assertSame(1, User::query()->where('cpf', '52998224725')->count());
    }

    public function test_cpf_ja_cadastrado_como_cidadao_com_senha_e_recusado(): void
    {
        Cidadao::factory()->create(['cpf' => '52998224725']);

        $this->post('/portal/register', $this->dadosCadastro())
            ->assertSessionHasErrors('cpf');
    }

    public function test_cadastro_assume_o_registro_criado_no_balcao(): void
    {
        $balcao = Cidadao::factory()->semSenha()->create([
            'cpf' => '52998224725',
            'email' => 'balcao@example.com',
        ]);

        $this->post('/portal/register', $this->dadosCadastro())->assertRedirect();

        $this->assertSame(1, Cidadao::query()->where('cpf', '52998224725')->count());
        $balcao->refresh();
        $this->assertTrue($balcao->temSenha());
        $this->assertSame('maria@example.com', $balcao->email);
    }

    public function test_registro_do_balcao_nao_autentica_sem_senha(): void
    {
        Cidadao::factory()->semSenha()->create(['cpf' => '52998224725']);

        $this->post('/portal/login', ['cpf' => '529.982.247-25', 'password' => 'qualquer'])
            ->assertSessionHasErrors();
        $this->assertGuest('web');
    }

    public function test_login_do_portal_so_autentica_cidadao(): void
    {
        $servidor = User::factory()->create(['cpf' => '52998224725']);

        $this->post('/portal/login', ['cpf' => '529.982.247-25', 'password' => 'password'])
            ->assertSessionHasErrors();
        $this->assertGuest('web');

        Cidadao::factory()->create(['cpf' => '52998224725']);

        $this->post('/portal/login', ['cpf' => '529.982.247-25', 'password' => 'password']);
        $this->assertAuthenticated('web');
        $this->assertNotSame($servidor::class, auth('web')->user()::class);
    }

    public function test_login_da_gestao_so_autentica_servidor(): void
    {
        Cidadao::factory()->create(['cpf' => '52998224725', 'email' => 'maria@example.com']);

        $this->post('/gestao/login', ['identifier' => 'maria@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('identifier');
        $this->assertGuest('gestao');

        $this->post('/gestao/login', ['identifier' => '52998224725', 'password' => 'password'])
            ->assertSessionHasErrors('identifier');
        $this->assertGuest('gestao');
    }

    public function test_recuperacao_de_senha_do_portal_ignora_servidor(): void
    {
        User::factory()->create(['email' => 'so-servidor@example.com']);

        $status = Password::broker('cidadaos')->sendResetLink(['email' => 'so-servidor@example.com']);

        $this->assertSame(Password::INVALID_USER, $status);
    }

    public function test_sessao_de_cidadao_existente_continua_valida_no_painel(): void
    {
        $cidadao = Cidadao::factory()->create();

        $this->actingAs($cidadao, 'web')->get('/portal/painel')->assertOk();
    }

    public function test_sessao_de_servidor_nao_autentica_no_portal(): void
    {
        $servidor = User::factory()->create();

        $this->actingAs($servidor, 'gestao')->get('/portal/painel')->assertRedirect(route('login'));
    }

    public function test_id_de_servidor_na_sessao_nao_resolve_cidadao_sem_senha_de_mesmo_id(): void
    {
        $servidor = User::factory()->create();
        $espelho = Cidadao::factory()->semSenha()->create(['id' => $servidor->id]);

        $this->withSession([auth('web')->getName() => $espelho->id])
            ->get('/portal/painel')
            ->assertRedirect(route('login'));
    }

    public function test_recuperacao_pelo_broker_do_portal_encontra_cidadao_com_senha(): void
    {
        Notification::fake();
        Cidadao::factory()->create(['email' => 'maria@example.com']);

        $status = Password::broker('cidadaos')->sendResetLink(['email' => 'maria@example.com']);

        $this->assertSame(Password::RESET_LINK_SENT, $status);
        Notification::assertSentTo(Cidadao::query()->first(), ResetPasswordQueued::class);
    }

    public function test_cadastro_que_assume_balcao_exige_nova_verificacao_de_email(): void
    {
        Notification::fake();
        $balcao = Cidadao::factory()->semSenha()->create(['cpf' => '52998224725']);
        $balcao->forceFill(['email_verified_at' => now()])->save();

        $this->post('/portal/register', $this->dadosCadastro());

        $balcao->refresh();
        $this->assertTrue($balcao->temSenha());
        $this->assertNull($balcao->email_verified_at);
        Notification::assertSentTo($balcao, VerifyEmailQueued::class);
    }

    public function test_cadastro_recusa_assumir_registro_de_balcao_inativo(): void
    {
        Cidadao::factory()->semSenha()->inactive()->create(['cpf' => '52998224725']);

        $this->post('/portal/register', $this->dadosCadastro())->assertSessionHasErrors('cpf');

        $this->assertFalse(Cidadao::query()->where('cpf', '52998224725')->first()->temSenha());
    }

    public function test_forgot_password_do_portal_com_email_de_servidor_nao_envia_nada(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'so-servidor@example.com']);

        $deServidor = $this->respostaDoForgotPasswordDoPortal('so-servidor@example.com');
        $inexistente = $this->respostaDoForgotPasswordDoPortal('ninguem@example.com');

        Notification::assertNothingSent();
        // A resposta ao e-mail de servidor é a mesma do e-mail inexistente: o portal
        // não revela que o endereço pertence a uma conta da gestão.
        // (e-mail inexistente responde como envio real; RespostaRecuperacaoSenhaSemRevelarConta).
        $this->assertSame(302, $inexistente['status']);
        $this->assertSame([], $inexistente['erros']);
        $this->assertSame(__(Password::RESET_LINK_SENT), $inexistente['flash']);
        $this->assertSame($inexistente, $deServidor);
    }

    /**
     * Requisição isolada: o store de sessão do teste é compartilhado entre
     * requisições, então a sessão é capturada e esvaziada a cada chamada.
     *
     * @return array{status: int, destino: ?string, erros: array<string, list<string>>, flash: mixed}
     */
    private function respostaDoForgotPasswordDoPortal(string $email): array
    {
        $this->flushSession();

        $resposta = $this->from('/portal/forgot-password')->post('/portal/forgot-password', ['email' => $email]);
        $sessao = $resposta->baseResponse->getSession();

        $retrato = [
            'status' => $resposta->getStatusCode(),
            'destino' => $resposta->headers->get('Location'),
            'erros' => $sessao->get('errors')?->getBag('default')->toArray() ?? [],
            'flash' => $sessao->get('status'),
        ];

        $this->flushSession();

        return $retrato;
    }

    public function test_forgot_password_da_gestao_ignora_cidadao_com_o_mesmo_email(): void
    {
        Notification::fake();
        Cidadao::factory()->create(['email' => 'so-cidadao@example.com']);

        $this->post('/gestao/forgot-password', ['email' => 'so-cidadao@example.com'])->assertRedirect();

        Notification::assertNothingSent();
    }
}
