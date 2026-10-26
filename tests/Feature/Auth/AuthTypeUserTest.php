<?php

namespace Tests\Feature\Auth;

use App\Enums\AuthType;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthTypeUserTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_usuario_nasce_com_autenticacao_local_por_padrao(): void
    {
        $user = User::factory()->create();

        $this->assertSame(AuthType::Local, $user->auth_type);
        $this->assertNull($user->ad_username);
        $this->assertFalse($user->isAdAccount());
    }

    public function test_usuario_ad_tem_conta_vinculada_e_e_reconhecido(): void
    {
        $user = User::factory()->ad('seuusuarioad')->create();

        $this->assertSame(AuthType::Ad, $user->fresh()->auth_type);
        $this->assertSame('seuusuarioad', $user->ad_username);
        $this->assertTrue($user->isAdAccount());
    }

    public function test_conta_portal_servicos_grava_e_le_o_tipo(): void
    {
        $user = User::factory()->create(['auth_type' => AuthType::PortalServicos]);

        $this->assertSame(AuthType::PortalServicos, $user->fresh()->auth_type);
        $this->assertFalse($user->isAdAccount());
        $this->assertSame('Portal de Serviços da SEDUR', AuthType::PortalServicos->label());
    }

    public function test_conta_portal_servicos_entra_no_scope_usuario_portal_e_nao_na_equipe(): void
    {
        $user = User::factory()->create(['auth_type' => AuthType::PortalServicos]);

        $this->assertTrue(User::query()->usuarioPortal()->whereKey($user->id)->exists());
        $this->assertFalse(User::query()->equipeSedur()->whereKey($user->id)->exists());
    }

    public function test_ad_username_e_unico(): void
    {
        User::factory()->ad('seuusuarioad')->create();

        $this->expectException(QueryException::class);

        User::factory()->ad('seuusuarioad')->create();
    }
}
