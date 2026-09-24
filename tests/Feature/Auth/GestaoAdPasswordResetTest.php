<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\GestaoResetPasswordQueued;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class GestaoAdPasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_conta_ad_nao_recebe_link_de_recuperacao_e_resposta_permanece_generica(): void
    {
        Notification::fake();

        $servidorAd = User::factory()->administrador()->ad('filipefalcao')->create();
        $servidorLocal = User::factory()->administrador()->create();

        $respostaAd = $this->post('/gestao/forgot-password', ['email' => $servidorAd->email]);
        $respostaLocal = $this->post('/gestao/forgot-password', ['email' => $servidorLocal->email]);

        $respostaAd->assertSessionHas('status', 'Se a conta tiver acesso ao console, enviamos as instruções de recuperação.');
        $respostaLocal->assertSessionHas('status', 'Se a conta tiver acesso ao console, enviamos as instruções de recuperação.');

        Notification::assertNotSentTo($servidorAd, GestaoResetPasswordQueued::class);
        Notification::assertSentTo($servidorLocal, GestaoResetPasswordQueued::class);
    }

    public function test_conta_ad_nao_redefine_senha_mesmo_com_token_valido(): void
    {
        $servidor = User::factory()->administrador()->ad('filipefalcao')->create();
        $hashAntes = $servidor->password;

        $token = Password::broker('users')->createToken($servidor);

        $this->post('/gestao/reset-password', [
            'email' => $servidor->email,
            'token' => $token,
            'password' => 'NovaSenha123',
            'password_confirmation' => 'NovaSenha123',
        ])->assertSessionHasErrors('email');

        $this->assertSame($hashAntes, $servidor->fresh()->password);
        $this->assertFalse(Hash::check('NovaSenha123', $servidor->fresh()->password));
    }

    public function test_conta_ad_nao_altera_senha_pela_area_da_conta(): void
    {
        $servidor = User::factory()->administrador()->ad('filipefalcao')->withAcceptedLgpdTerm()->create();

        $this->actingAs($servidor, 'gestao');

        $this->put('/gestao/conta/senha', [
            'current_password' => 'qualquer',
            'password' => 'NovaSenha123',
            'password_confirmation' => 'NovaSenha123',
        ])->assertSessionHasErrors('current_password');

        $this->assertFalse(Hash::check('NovaSenha123', $servidor->fresh()->password));
    }
}
