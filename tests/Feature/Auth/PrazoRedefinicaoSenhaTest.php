<?php

namespace Tests\Feature\Auth;

use App\Models\Cidadao;
use App\Models\Parameter;
use App\Models\User;
use App\Notifications\GestaoResetPasswordQueued;
use App\Notifications\ResetPasswordQueued;
use App\Providers\AppServiceProvider;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PrazoRedefinicaoSenhaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function aplicarPrazo(int $minutos): void
    {
        $this->seed(ParameterSeeder::class);
        Parameter::query()->where('key', 'security.password_reset_expire')->first()->update(['value' => (string) $minutos]);

        AppServiceProvider::aplicarPrazoDeRedefinicaoDeSenha();

        // Os brokers guardam o prazo ao serem resolvidos: força a releitura da configuração.
        $this->app->forgetInstance('auth.password');
        Password::clearResolvedInstance('auth.password');
    }

    private function textoDoEmail(ResetPasswordQueued $notificacao, object $notifiable): string
    {
        return (string) $notificacao->toMail($notifiable)->render();
    }

    public function test_parametro_vale_para_os_dois_brokers(): void
    {
        $this->aplicarPrazo(15);

        $this->assertSame(15, config('auth.passwords.users.expire'));
        $this->assertSame(15, config('auth.passwords.cidadaos.expire'));
    }

    public function test_token_do_portal_expira_no_prazo_do_parametro(): void
    {
        $this->aplicarPrazo(15);
        $cidadao = Cidadao::factory()->create();

        $token = Password::broker('cidadaos')->createToken($cidadao);

        $this->travel(14)->minutes();
        $this->assertTrue(Password::broker('cidadaos')->tokenExists($cidadao, $token));
        $this->travel(2)->minutes();
        $this->assertFalse(Password::broker('cidadaos')->tokenExists($cidadao, $token));
    }

    public function test_email_de_cada_ambiente_cita_o_prazo_do_proprio_broker(): void
    {
        config(['auth.passwords.cidadaos.expire' => 15, 'auth.passwords.users.expire' => 45]);

        $cidadao = Cidadao::factory()->create();
        $servidor = User::factory()->create();

        $portal = new ResetPasswordQueued('token-portal');
        $portal->freezeUrlFor($cidadao);
        $gestao = new GestaoResetPasswordQueued('token-gestao');
        $gestao->freezeUrlFor($servidor);

        $this->assertStringContainsString('válido por 15 minutos', $this->textoDoEmail($portal, $cidadao));
        $this->assertStringContainsString('válido por 45 minutos', $this->textoDoEmail($gestao, $servidor));
    }
}
