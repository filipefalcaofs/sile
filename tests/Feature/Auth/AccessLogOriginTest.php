<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AccessLogOriginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_registra_a_origem_definida_na_request(): void
    {
        $user = User::factory()->create();

        request()->attributes->set('auth_origin', 'ad');

        event(new Login('gestao', $user, false));

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $user->id,
            'event' => 'login',
            'origin' => 'ad',
        ]);
    }

    public function test_login_sem_origem_definida_grava_nulo(): void
    {
        $user = User::factory()->create();

        event(new Login('gestao', $user, false));

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $user->id,
            'event' => 'login',
            'origin' => null,
        ]);
    }

    public function test_falha_registra_a_origem_presente_nas_credenciais(): void
    {
        event(new Failed('gestao', null, ['email' => 'servidor@sedur.ba.gov.br', 'origin' => 'ad']));

        $this->assertDatabaseHas('access_logs', [
            'event' => 'falha',
            'origin' => 'ad',
        ]);
    }

    public function test_falha_sem_origem_nas_credenciais_grava_nulo(): void
    {
        event(new Failed('gestao', null, ['email' => 'servidor@sedur.ba.gov.br']));

        $this->assertDatabaseHas('access_logs', [
            'event' => 'falha',
            'origin' => null,
        ]);
    }
}
