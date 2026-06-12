<?php

namespace Tests\Feature\Parameters;

use App\Models\Activity;
use App\Models\Parameter;
use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ParameterHistoryTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_historico_exibe_anterior_novo_responsavel_e_data(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'gestao')->put(route('gestao.parametros.update', 'ui.access_history.per_page'), ['value' => '5']);
        $this->actingAs($admin, 'gestao')->put(route('gestao.parametros.update', 'ui.access_history.per_page'), ['value' => '10']);

        $this->actingAs($admin, 'gestao')
            ->get(route('gestao.parametros.historico', 'ui.access_history.per_page'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/parametros/historico')
                ->has('entries.data', 2)
                ->where('entries.data.0.valor_anterior', '5')
                ->where('entries.data.0.valor_novo', '10')
                ->where('entries.data.0.responsavel', $admin->name)
                ->whereNot('entries.data.0.data', null));
    }

    public function test_historico_de_sensivel_mascara_valores(): void
    {
        $admin = $this->admin();

        Parameter::factory()->sensitive()->create([
            'key' => 'integracao.sefaz.token',
            'group' => 'integracoes',
            'value' => 'token-original',
            'validation_rules' => ['required', 'string'],
        ]);

        $this->actingAs($admin, 'gestao')
            ->put(route('gestao.parametros.update', 'integracao.sefaz.token'), ['value' => 'token-secreto-003'])
            ->assertRedirect();

        $this->actingAs($admin, 'gestao')
            ->get(route('gestao.parametros.historico', 'integracao.sefaz.token'))
            ->assertOk()
            ->assertDontSee('token-secreto-003')
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/parametros/historico')
                ->where('entries.data.0.valor_anterior', '[criptografado]')
                ->where('entries.data.0.valor_novo', '[criptografado]'));

        $serialized = Activity::query()->where('log_name', 'parametros')->get()->toJson();

        $this->assertStringNotContainsString('token-secreto-003', $serialized);
    }

    public function test_historico_exige_permissao(): void
    {
        $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();

        $this->actingAs($gestor, 'gestao')
            ->get('/gestao/parametros/ui.access_history.per_page/historico')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
            'causer_id' => $gestor->id,
        ]);
    }
}
