<?php

namespace Tests\Feature\Parameters;

use App\Models\Parameter;
use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MapaEncaminhamentoValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ParameterSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    public function test_rejeita_typo_no_fluxo(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/parametros/risco.mapa_encaminhamento', [
                'value' => '{"baixo_a":"expreso","baixo_b":"expresso","alto":"analise"}',
            ])
            ->assertSessionHasErrors('value');

        $this->assertNull(Parameter::query()->where('key', 'risco.mapa_encaminhamento')->first()?->pendingProposal);
    }

    public function test_rejeita_chave_inexistente(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/parametros/risco.mapa_encaminhamento', [
                'value' => '{"baixo_a":"expresso","baixo_b":"expresso","medio":"analise"}',
            ])
            ->assertSessionHasErrors('value');
    }

    public function test_rejeita_mapa_incompleto(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/parametros/risco.mapa_encaminhamento', [
                'value' => '{"baixo_a":"expresso","alto":"analise"}',
            ])
            ->assertSessionHasErrors('value');
    }

    public function test_rejeita_lista_json(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/parametros/risco.mapa_encaminhamento', [
                'value' => '["expresso","analise"]',
            ])
            ->assertSessionHasErrors('value');
    }

    public function test_mapa_valido_abre_proposta(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/parametros/risco.mapa_encaminhamento', [
                'value' => '{"baixo_a":"analise","baixo_b":"expresso","alto":"analise"}',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $parameter = Parameter::query()->where('key', 'risco.mapa_encaminhamento')->firstOrFail();
        $this->assertNull($parameter->value);
        $this->assertNotNull($parameter->pendingProposal);
        $this->assertSame(
            '{"baixo_a":"analise","baixo_b":"expresso","alto":"analise"}',
            $parameter->pendingProposal->proposed_value,
        );
    }
}
