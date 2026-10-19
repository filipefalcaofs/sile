<?php

namespace Tests\Feature\Gestao;

use App\Enums\AuthType;
use App\Models\User;
use App\Notifications\GestaoResetPasswordQueued;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserCreationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function gestorLogado(): User
    {
        $gestor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($gestor, 'gestao');

        return $gestor;
    }

    /**
     * @return array<string, string>
     */
    private function dados(array $override = []): array
    {
        return array_merge([
            'name' => 'Maria da Silva',
            'email' => 'maria@exemplo.com.br',
            'cpf' => '111.444.777-35',
            'phone' => '(71) 99999-1234',
            'role' => 'Analista Servidor',
        ], $override);
    }

    public function test_gestor_cria_conta_local_com_papel_e_envia_link_para_definir_senha(): void
    {
        Notification::fake();
        $this->gestorLogado();

        $this->post('/gestao/usuarios', $this->dados())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $criado = User::where('email', 'maria@exemplo.com.br')->firstOrFail();

        $this->assertSame('Maria da Silva', $criado->name);
        $this->assertSame('11144477735', $criado->cpf);
        $this->assertSame(AuthType::Local, $criado->auth_type);
        $this->assertNull($criado->ad_username);
        $this->assertNotNull($criado->email_verified_at);
        $this->assertTrue($criado->hasRole('Analista Servidor'));

        Notification::assertSentTo($criado, GestaoResetPasswordQueued::class);
    }

    public function test_conta_nasce_com_senha_inutilizavel(): void
    {
        Notification::fake();
        $this->gestorLogado();

        $this->post('/gestao/usuarios', $this->dados());

        $criado = User::where('email', 'maria@exemplo.com.br')->firstOrFail();

        $this->assertNotEmpty($criado->password);
        $this->assertFalse(password_verify('password', $criado->password));
    }

    public function test_criacao_e_auditada_como_dado_pessoal(): void
    {
        Notification::fake();
        $this->gestorLogado();

        $this->post('/gestao/usuarios', $this->dados());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'usuarios',
            'event' => 'usuario-criado',
            'personal_data' => true,
        ]);
    }

    public function test_telefone_e_opcional(): void
    {
        Notification::fake();
        $this->gestorLogado();

        $this->post('/gestao/usuarios', $this->dados(['phone' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(User::where('email', 'maria@exemplo.com.br')->firstOrFail()->phone);
    }

    public function test_campos_obrigatorios(): void
    {
        $this->gestorLogado();

        $this->post('/gestao/usuarios', [])
            ->assertSessionHasErrors(['name', 'email', 'cpf', 'role']);
    }

    public function test_cpf_precisa_ser_valido(): void
    {
        $this->gestorLogado();

        $this->post('/gestao/usuarios', $this->dados(['cpf' => '111.111.111-11']))
            ->assertSessionHasErrors('cpf');
    }

    public function test_email_e_cpf_precisam_ser_unicos(): void
    {
        $this->gestorLogado();
        User::factory()->create(['email' => 'maria@exemplo.com.br', 'cpf' => '11144477735']);

        $this->post('/gestao/usuarios', $this->dados())
            ->assertSessionHasErrors(['email', 'cpf']);
    }

    public function test_papel_precisa_existir(): void
    {
        $this->gestorLogado();

        $this->post('/gestao/usuarios', $this->dados(['role' => 'papel-inexistente']))
            ->assertSessionHasErrors('role');
    }

    public function test_telefone_precisa_ter_ddd(): void
    {
        $this->gestorLogado();

        $this->post('/gestao/usuarios', $this->dados(['phone' => '1234']))
            ->assertSessionHasErrors('phone');
    }

    public function test_quem_nao_tem_permissao_nao_cria_usuario(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $this->actingAs($analista, 'gestao');

        $this->post('/gestao/usuarios', $this->dados())->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'maria@exemplo.com.br']);
    }

    public function test_tela_de_usuarios_entrega_papeis_para_o_formulario(): void
    {
        $this->gestorLogado();

        $this->get('/gestao/usuarios')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('gestao/usuarios/index')
                ->has('roles'));
    }
}
