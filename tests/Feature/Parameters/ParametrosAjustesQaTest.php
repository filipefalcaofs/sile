<?php

namespace Tests\Feature\Parameters;

use App\Models\AiConfiguration;
use App\Models\Parameter;
use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ParametrosAjustesQaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    public function test_acesso_negado_aos_parametros_orienta_em_portugues(): void
    {
        $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();

        $this->actingAs($gestor, 'gestao')
            ->get('/gestao/parametros')
            ->assertForbidden()
            ->assertSee('Você não tem permissão para acessar esta tela')
            ->assertSee('administrador do Viabiliza')
            ->assertDontSee('User does not have the right permissions');
    }

    public function test_migration_cria_o_interruptor_do_portal_sem_depender_do_seeder(): void
    {
        Parameter::query()->where('key', 'features.portal_servicos')->delete();

        (require database_path('migrations/2026_10_27_160100_cria_parametro_portal_servicos.php'))->up();

        $parametro = Parameter::query()->where('key', 'features.portal_servicos')->first();

        $this->assertNotNull($parametro);
        $this->assertSame('features', $parametro->group);
        $this->assertSame('boolean', $parametro->type);
        $this->assertSame('0', $parametro->default_value);

        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/parametros')
            ->assertInertia(fn (Assert $page) => $page
                ->where('groups.features', fn ($itens) => collect($itens)->contains('key', 'features.portal_servicos')));
    }

    public function test_migration_do_interruptor_do_portal_e_idempotente_e_preserva_valor_gravado(): void
    {
        $this->seed(ParameterSeeder::class);
        Parameter::query()->where('key', 'features.portal_servicos')->update(['value' => '1']);

        (require database_path('migrations/2026_10_27_160100_cria_parametro_portal_servicos.php'))->up();

        $this->assertSame(1, Parameter::query()->where('key', 'features.portal_servicos')->count());
        $this->assertSame('1', Parameter::query()->where('key', 'features.portal_servicos')->value('value'));
    }

    public function test_primeira_alteracao_registra_o_padrao_como_valor_anterior(): void
    {
        $this->seed(ParameterSeeder::class);
        $admin = $this->admin();
        $padrao = Parameter::query()->where('key', 'security.login.max_attempts')->firstOrFail()->default_value;

        $this->assertNull(Parameter::query()->where('key', 'security.login.max_attempts')->value('value'));

        $this->actingAs($admin, 'gestao')->put(route('gestao.parametros.update', 'security.login.max_attempts'), ['value' => '7']);

        $this->actingAs($admin, 'gestao')
            ->get(route('gestao.parametros.historico', 'security.login.max_attempts'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries.data.0.valor_anterior', $padrao)
                ->where('entries.data.0.valor_novo', '7'));
    }

    public function test_aprovacao_decisoria_registra_o_padrao_como_valor_anterior(): void
    {
        $this->seed(ParameterSeeder::class);
        $parametro = Parameter::query()->where('key', 'renovacao.intervalo_meses')->firstOrFail();
        $autor = $this->admin();
        $aprovador = $this->admin();

        $this->actingAs($autor, 'gestao')->put(route('gestao.parametros.update', $parametro->key), ['value' => '9']);
        $this->actingAs($aprovador, 'gestao')->post(route('gestao.parametros.aprovar', $parametro->key))->assertRedirect();

        $this->actingAs($aprovador, 'gestao')
            ->get(route('gestao.parametros.historico', $parametro->key))
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries.data.0.valor_anterior', $parametro->default_value)
                ->where('entries.data.0.valor_novo', '9'));
    }

    public function test_teste_de_conexao_com_falha_mostra_o_motivo_ao_usuario(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'invalid'], 401)]);
        $config = AiConfiguration::factory()->create(['base_url' => 'https://api.openai.com/v1']);

        $this->actingAs($this->admin(), 'gestao')
            ->post("/gestao/config-ia/{$config->id}/testar")
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $mensagem) => str_starts_with($mensagem, 'Falha na conexão'));
    }

    public function test_exportacao_traz_o_nome_do_grupo_com_acento(): void
    {
        $this->seed(ParameterSeeder::class);

        $conteudo = $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/parametros?formato=csv')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Segurança', $conteudo);
        $this->assertStringContainsString('Integrações', $conteudo);
        $this->assertStringNotContainsString('seguranca;', $conteudo);
    }

    public function test_descricoes_do_catalogo_sem_travessao_nem_codigo_interno(): void
    {
        $this->seed(ParameterSeeder::class);

        foreach (Parameter::query()->pluck('description', 'key') as $chave => $descricao) {

            $this->assertStringNotContainsString('—', $descricao, $chave);
            $this->assertDoesNotMatchRegularExpression('/\b(HU-\d+|EP\d+|Módulo \d)/u', $descricao, $chave);
            $this->assertStringStartsNotWith('Exigir', $descricao, $chave);
        }
    }

    public function test_migration_de_descricoes_atualiza_o_banco_e_reverte(): void
    {
        $this->seed(ParameterSeeder::class);
        $migration = require database_path('migrations/2026_10_27_160000_padroniza_descricoes_dos_parametros.php');

        Parameter::query()->where('key', 'security.password.require_numbers')->update(['description' => 'Exigir números na senha']);

        $migration->up();

        $this->assertSame('Exige números na senha', Parameter::query()->where('key', 'security.password.require_numbers')->value('description'));

        $migration->down();

        $this->assertSame('Exigir números na senha', Parameter::query()->where('key', 'security.password.require_numbers')->value('description'));
    }
}
