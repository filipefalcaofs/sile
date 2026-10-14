<?php

namespace Tests\Feature\Analise;

use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdministradorNaoConcluiAnaliseTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_administrador_recebe_403_ao_concluir_analise(): void
    {
        $administrador = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $processo = ViabilityRequest::factory()->create();

        $this->actingAs($administrador, 'gestao')
            ->post("/gestao/processos/{$processo->id}/ficha/concluir-processo", ['resultado' => 'deferido'])
            ->assertForbidden();
    }
}
