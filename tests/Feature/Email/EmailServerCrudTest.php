<?php

namespace Tests\Feature\Email;

use App\Mail\EmailServerTestMail;
use App\Models\EmailServer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailServerCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function administrador(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    public function test_administrador_acessa_a_tela_de_configuracao(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/config-email')
            ->assertOk();
    }

    public function test_sem_a_permissao_o_acesso_e_negado(): void
    {
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/config-email')
            ->assertForbidden();
    }

    public function test_cadastra_servidor_e_define_como_padrao_desmarcando_o_anterior(): void
    {
        $anterior = EmailServer::factory()->default()->create();

        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/config-email', [
                'name' => 'SEDUR SMTP',
                'driver' => 'smtp',
                'host' => 'smtp.sedur.test',
                'port' => 587,
                'encryption' => 'tls',
                'timeout' => 30,
                'username' => 'sedur',
                'password' => 'segredo-1234',
                'from_address' => 'no-reply@sedur.test',
                'from_name' => 'SEDUR',
                'active' => true,
                'is_default' => true,
            ])
            ->assertRedirect();

        $novo = EmailServer::query()->where('name', 'SEDUR SMTP')->firstOrFail();

        $this->assertTrue($novo->is_default);
        $this->assertFalse($anterior->fresh()->is_default);
        $this->assertSame('segredo-1234', $novo->password);
    }

    public function test_atualizar_com_senha_em_branco_mantem_a_senha_atual(): void
    {
        $server = EmailServer::factory()->create(['password' => 'senha-original-1']);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/config-email/{$server->id}", [
                'name' => $server->name,
                'driver' => 'smtp',
                'host' => $server->host,
                'port' => $server->port,
                'encryption' => $server->encryption,
                'timeout' => $server->timeout,
                'username' => $server->username,
                'password' => '',
                'from_address' => $server->from_address,
                'from_name' => $server->from_name,
                'active' => true,
                'is_default' => false,
            ])
            ->assertRedirect();

        $this->assertSame('senha-original-1', $server->fresh()->password);
    }

    public function test_testar_conexao_dispara_envio_real(): void
    {
        Mail::fake();
        $server = EmailServer::factory()->create();

        $this->actingAs($this->administrador(), 'gestao')
            ->post("/gestao/config-email/{$server->id}/testar", [
                'recipient' => 'destino@sedur.test',
            ])
            ->assertRedirect();

        Mail::assertSent(EmailServerTestMail::class);
    }

    public function test_excluir_remove_o_servidor(): void
    {
        $server = EmailServer::factory()->create();

        $this->actingAs($this->administrador(), 'gestao')
            ->delete("/gestao/config-email/{$server->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('email_servers', ['id' => $server->id]);
    }
}
