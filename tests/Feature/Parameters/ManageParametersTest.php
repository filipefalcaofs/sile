<?php

namespace Tests\Feature\Parameters;

use App\Models\Activity;
use App\Models\Parameter;
use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManageParametersTest extends TestCase
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

    private function sensitiveParameter(string $value = 'segredo-sefaz-001'): Parameter
    {
        return Parameter::factory()->sensitive()->create([
            'key' => 'integracao.sefaz.token',
            'group' => 'integracoes',
            'value' => $value,
            'validation_rules' => ['required', 'string'],
        ]);
    }

    public function test_administrador_ve_parametros_agrupados(): void
    {
        $this->actingAs($this->admin())
            ->get('/gestao/parametros')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/parametros/index')
                ->has('groups.features')
                ->has('groups.seguranca')
                ->has('groups.ui')
                ->has('groups.seguranca.0', fn (Assert $item) => $item->hasAll([
                    'key',
                    'type',
                    'description',
                    'sensitive',
                    'requires_connection_test',
                    'default_value',
                    'value',
                    'has_admin_value',
                    'updated_at',
                ]))
                ->where('groups.seguranca.0.key', 'security.login.max_attempts')
                ->where('groups.seguranca.0.type', 'integer')
                ->where('groups.seguranca.0.description', 'Tentativas de login antes do bloqueio temporário')
                ->where('groups.seguranca.0.value', null)
                ->where('groups.seguranca.0.default_value', '5')
                ->where('groups.seguranca.0.sensitive', false));
    }

    public function test_atualiza_parametro_valido(): void
    {
        $this->actingAs($this->admin())
            ->put('/gestao/parametros/ui.access_history.per_page', ['value' => '5'])
            ->assertRedirect();

        $this->assertSame(
            '5',
            Parameter::query()->where('key', 'ui.access_history.per_page')->value('value'),
        );
    }

    public function test_valor_invalido_e_rejeitado_pelas_regras_do_catalogo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put('/gestao/parametros/security.login.max_attempts', ['value' => '0'])
            ->assertSessionHasErrors('value');

        $this->assertNull(Parameter::query()->where('key', 'security.login.max_attempts')->value('value'));

        $this->actingAs($admin)
            ->put('/gestao/parametros/security.login.max_attempts', ['value' => 'abc'])
            ->assertSessionHasErrors('value');

        $this->assertNull(Parameter::query()->where('key', 'security.login.max_attempts')->value('value'));
    }

    public function test_alteracao_e_auditada_com_valor_anterior_e_novo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/gestao/parametros/ui.access_history.per_page', ['value' => '5']);
        $this->actingAs($admin)->put('/gestao/parametros/ui.access_history.per_page', ['value' => '10']);

        $activities = Activity::query()
            ->where('log_name', 'parametros')
            ->where('event', 'parametro-alterado')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $activities);

        $last = $activities->last();

        $this->assertSame('ui.access_history.per_page', $last->properties['key']);
        $this->assertSame('5', $last->properties['valor_anterior']);
        $this->assertSame('10', $last->properties['valor_novo']);
        $this->assertSame($admin->id, $last->causer_id);
    }

    public function test_parametro_sensivel_nunca_e_devolvido_em_claro(): void
    {
        $this->sensitiveParameter();

        $this->actingAs($this->admin())
            ->get('/gestao/parametros')
            ->assertOk()
            ->assertDontSee('segredo-sefaz-001')
            ->assertInertia(fn (Assert $page) => $page
                ->where('groups.integracoes.0.key', 'integracao.sefaz.token')
                ->where('groups.integracoes.0.value', null)
                ->where('groups.integracoes.0.sensitive', true)
                ->where('groups.integracoes.0.has_admin_value', true));
    }

    public function test_atualizar_sensivel_grava_criptografado(): void
    {
        $this->sensitiveParameter();

        $this->actingAs($this->admin())
            ->put('/gestao/parametros/integracao.sefaz.token', ['value' => 'novo-token-002'])
            ->assertRedirect();

        $raw = DB::table('parameters')->where('key', 'integracao.sefaz.token')->value('value');

        $this->assertStringNotContainsString('novo-token-002', $raw);
        $this->assertSame(
            'novo-token-002',
            Parameter::query()->where('key', 'integracao.sefaz.token')->first()->value,
        );
    }

    public function test_sensivel_com_campo_vazio_mantem_o_valor_atual(): void
    {
        $this->sensitiveParameter();

        $this->actingAs($this->admin())
            ->put('/gestao/parametros/integracao.sefaz.token', ['value' => ''])
            ->assertRedirect();

        $this->assertSame(
            'segredo-sefaz-001',
            Parameter::query()->where('key', 'integracao.sefaz.token')->first()->value,
        );
        $this->assertSame(
            0,
            Activity::query()->where('log_name', 'parametros')->where('event', 'parametro-alterado')->count(),
        );
    }

    public function test_gestor_nao_mantem_parametros(): void
    {
        $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();

        $this->actingAs($gestor)
            ->get('/gestao/parametros')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
            'causer_id' => $gestor->id,
        ]);
    }
}
