<?php

namespace Tests\Feature\Motor;

use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Motor\ReprocessarEtapasService;
use App\Services\Motor\ReprocessoResultado;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReprocessarProcessoEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_analista_reprocessa_o_processo_e_ve_a_mensagem(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $processo = ViabilityRequest::factory()->create();

        $servico = Mockery::mock(ReprocessarEtapasService::class);
        $servico->shouldReceive('reprocessar')
            ->once()
            ->withArgs(fn (ViabilityRequest $p, ?User $u) => $p->is($processo) && $u?->is($analista))
            ->andReturn(new ReprocessoResultado(true, null, 'em_analise', 'Etapas reprocessadas e veredito reavaliado.'));
        $this->app->instance(ReprocessarEtapasService::class, $servico);

        $this->actingAs($analista, 'gestao')
            ->post(route('gestao.processos.reprocessar', $processo))
            ->assertRedirect()
            ->assertSessionHas('status', 'Etapas reprocessadas e veredito reavaliado.');
    }

    public function test_administrador_tambem_tem_a_permissao(): void
    {
        $administrador = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->assertTrue($administrador->can('reprocessar-processos'));
    }

    public function test_usuario_sem_a_permissao_recebe_403(): void
    {
        $usuario = User::factory()->withAcceptedLgpdTerm()->create();
        $processo = ViabilityRequest::factory()->create();

        $this->actingAs($usuario, 'gestao')
            ->post(route('gestao.processos.reprocessar', $processo))
            ->assertForbidden();
    }
}
