<?php

namespace Tests\Feature\Comunicacao;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NotificacaoSemEmailEntregavelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_email_de_solicitante_nao_identificado_nao_e_entregavel(): void
    {
        $this->assertFalse(User::factory()->make(['email' => 'x@regin.invalid'])->temEmailEntregavel());
        $this->assertTrue(User::factory()->make(['email' => 'maria@exemplo.com'])->temEmailEntregavel());
    }
}
