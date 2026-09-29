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

    public function test_ad_username_e_unico(): void
    {
        User::factory()->ad('seuusuarioad')->create();

        $this->expectException(QueryException::class);

        User::factory()->ad('seuusuarioad')->create();
    }
}
