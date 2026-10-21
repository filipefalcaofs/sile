<?php

namespace Tests\Feature\Gestao;

use App\Enums\AuthType;
use App\Models\User;
use App\Notifications\GestaoResetPasswordQueued;
use App\Notifications\ResetPasswordQueued;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserPasswordResetResendTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
    }

    private function gestorLogado(): User
    {
        $gestor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($gestor, 'gestao');

        return $gestor;
    }

    private function contaLocalDoConsole(): User
    {
        $user = User::factory()->create(['auth_type' => AuthType::Local, 'ad_username' => null]);
        $user->assignRole('Analista Servidor');

        return $user;
    }

    public function test_reenvia_link_do_console_para_conta_local_da_equipe(): void
    {
        $this->gestorLogado();
        $user = $this->contaLocalDoConsole();

        $this->post("/gestao/usuarios/{$user->id}/redefinicao-senha")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status')
            ->assertRedirect();

        Notification::assertSentTo($user, GestaoResetPasswordQueued::class);
        $this->assertDatabaseHas('email_logs', [
            'recipient_email' => $user->email,
            'notification_class' => GestaoResetPasswordQueued::class,
        ]);
    }

    public function test_usuario_do_portal_recebe_o_link_do_portal(): void
    {
        $this->gestorLogado();
        $user = User::factory()->create(['auth_type' => AuthType::Local, 'ad_username' => null]);

        $this->post("/gestao/usuarios/{$user->id}/redefinicao-senha")
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordQueued::class, fn ($n) => ! $n instanceof GestaoResetPasswordQueued);
        Notification::assertNotSentTo($user, GestaoResetPasswordQueued::class);
    }

    public function test_reenvio_e_auditado_como_dado_pessoal(): void
    {
        $this->gestorLogado();
        $user = $this->contaLocalDoConsole();

        $this->post("/gestao/usuarios/{$user->id}/redefinicao-senha");

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'usuarios',
            'event' => 'link-senha-reenviado',
            'personal_data' => true,
        ]);
    }

    public function test_conta_ad_nao_recebe_link(): void
    {
        $this->gestorLogado();
        $user = $this->contaLocalDoConsole();
        $user->forceFill(['auth_type' => AuthType::Ad, 'ad_username' => 'fulano'])->save();

        $this->post("/gestao/usuarios/{$user->id}/redefinicao-senha")
            ->assertSessionHas('error');

        Notification::assertNothingSent();
    }

    public function test_conta_inativa_nao_recebe_link(): void
    {
        $this->gestorLogado();
        $user = $this->contaLocalDoConsole();
        $user->forceFill(['inactivated_at' => now()])->save();

        $this->post("/gestao/usuarios/{$user->id}/redefinicao-senha")
            ->assertSessionHas('error');

        Notification::assertNothingSent();
    }

    public function test_segundo_reenvio_imediato_e_barrado_pelo_intervalo(): void
    {
        $this->gestorLogado();
        $user = $this->contaLocalDoConsole();

        $this->post("/gestao/usuarios/{$user->id}/redefinicao-senha")->assertSessionHas('status');
        $this->post("/gestao/usuarios/{$user->id}/redefinicao-senha")->assertSessionHas('error');

        Notification::assertSentToTimes($user, GestaoResetPasswordQueued::class, 1);
    }

    public function test_quem_nao_tem_permissao_nao_reenvia(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $this->actingAs($analista, 'gestao');
        $user = $this->contaLocalDoConsole();

        $this->post("/gestao/usuarios/{$user->id}/redefinicao-senha")->assertForbidden();

        Notification::assertNothingSent();
    }
}
