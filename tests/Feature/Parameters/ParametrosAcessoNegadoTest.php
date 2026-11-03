<?php

namespace Tests\Feature\Parameters;

use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ParametrosAcessoNegadoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ParameterSeeder::class);
    }

    public function test_acesso_negado_a_parametros_orienta_em_portugues_sem_mensagem_tecnica(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $resposta = $this->actingAs($analista, 'gestao')->get(route('gestao.parametros.index'));

        $resposta->assertForbidden()
            ->assertSee('Você não tem permissão para acessar esta tela.')
            ->assertSee('peça ao administrador do Viabiliza')
            ->assertDontSee('User does not have the right permissions');
    }
}
