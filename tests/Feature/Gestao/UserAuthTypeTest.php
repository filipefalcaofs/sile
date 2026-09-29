<?php

namespace Tests\Feature\Gestao;

use App\Enums\AuthType;
use App\Models\User;
use App\Notifications\GestaoResetPasswordQueued;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserAuthTypeTest extends TestCase
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

    public function test_gestor_vincula_conta_ao_ad_e_senha_local_deixa_de_valer(): void
    {
        $this->gestorLogado();
        $servidor = User::factory()->analista()->create();

        $response = $this->put("/gestao/usuarios/{$servidor->id}/autenticacao", [
            'auth_type' => 'ad',
            'ad_username' => 'seuusuarioad',
        ]);

        $response->assertSessionHasNoErrors();

        $servidor->refresh();
        $this->assertSame(AuthType::Ad, $servidor->auth_type);
        $this->assertSame('seuusuarioad', $servidor->ad_username);
        $this->assertFalse(Hash::check('password', $servidor->password));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'usuarios',
            'event' => 'autenticacao-alterada',
        ]);
    }

    public function test_conta_ad_exige_ad_username(): void
    {
        $this->gestorLogado();
        $servidor = User::factory()->analista()->create();

        $this->put("/gestao/usuarios/{$servidor->id}/autenticacao", [
            'auth_type' => 'ad',
        ])->assertSessionHasErrors('ad_username');
    }

    public function test_ad_username_precisa_ser_unico(): void
    {
        $this->gestorLogado();
        User::factory()->ad('seuusuarioad')->create();
        $outro = User::factory()->analista()->create();

        $this->put("/gestao/usuarios/{$outro->id}/autenticacao", [
            'auth_type' => 'ad',
            'ad_username' => 'seuusuarioad',
        ])->assertSessionHasErrors('ad_username');
    }

    public function test_conta_local_nao_aceita_ad_username(): void
    {
        $this->gestorLogado();
        $servidor = User::factory()->analista()->create();

        $this->put("/gestao/usuarios/{$servidor->id}/autenticacao", [
            'auth_type' => 'local',
            'ad_username' => 'seuusuarioad',
        ])->assertSessionHasErrors('ad_username');
    }

    public function test_conversao_para_local_envia_link_para_definir_nova_senha(): void
    {
        Notification::fake();
        $this->gestorLogado();
        $servidor = User::factory()->analista()->ad('seuusuarioad')->create();

        $this->put("/gestao/usuarios/{$servidor->id}/autenticacao", [
            'auth_type' => 'local',
        ])->assertSessionHasNoErrors();

        $servidor->refresh();
        $this->assertSame(AuthType::Local, $servidor->auth_type);
        $this->assertNull($servidor->ad_username);

        Notification::assertSentTo($servidor, GestaoResetPasswordQueued::class);
    }

    public function test_mesma_configuracao_nao_troca_senha_nem_envia_email(): void
    {
        Notification::fake();
        $this->gestorLogado();
        $servidor = User::factory()->analista()->ad('seuusuarioad')->create();
        $hashAntes = $servidor->password;

        $this->put("/gestao/usuarios/{$servidor->id}/autenticacao", [
            'auth_type' => 'ad',
            'ad_username' => 'seuusuarioad',
        ])->assertSessionHasNoErrors();

        $this->assertSame($hashAntes, $servidor->fresh()->password);
        Notification::assertNothingSent();
    }

    public function test_usuario_sem_permissao_nao_altera_autenticacao(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $this->actingAs($analista, 'gestao');

        $servidor = User::factory()->analista()->create();

        $this->put("/gestao/usuarios/{$servidor->id}/autenticacao", [
            'auth_type' => 'ad',
            'ad_username' => 'seuusuarioad',
        ])->assertForbidden();
    }
}
