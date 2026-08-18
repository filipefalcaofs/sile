<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SessionExpiredTest extends TestCase
{
    use RefreshDatabase;

    /**
     * CSRF é bypassado em ambiente de teste (runningUnitTests), então a
     * expiração é simulada lançando a mesma exceção do middleware real.
     */
    protected function registerExpiredRoute(): void
    {
        Route::post('rota-teste-419', function (): never {
            throw new TokenMismatchException('CSRF token mismatch.');
        })->middleware('web');
    }

    public function test_sessao_expirada_volta_a_pagina_anterior_com_aviso(): void
    {
        $this->registerExpiredRoute();

        $response = $this->from('/gestao/login')->post('rota-teste-419');

        $response->assertRedirect('/gestao/login');
        $response->assertSessionHas('error', 'Sua sessão expirou. Tente novamente.');
    }

    public function test_sessao_expirada_no_portal_tambem_recebe_aviso(): void
    {
        $this->registerExpiredRoute();

        $response = $this->from('/portal/login')->post('rota-teste-419');

        $response->assertRedirect('/portal/login');
        $response->assertSessionHas('error', 'Sua sessão expirou. Tente novamente.');
    }
}
