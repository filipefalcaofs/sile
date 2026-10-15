<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

        $this->post('/gestao/login', ['email' => 'maria@example.com', 'password' => 'password'])
            ->assertSessionHasErrors();
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
}
