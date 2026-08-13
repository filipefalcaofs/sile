<?php

namespace Tests\Feature\Ai;

use App\Models\AiConfiguration;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * CRUD administrável dos provedores de IA (Fase 14, Onda 0 — HU-014). Espelha o
 * irmão de e-mail (config-email) e cobre os requisitos de SEGURANÇA: autorização
 * (403 auditado no ponto único), credencial NUNCA reexibida em claro (DTO com
 * masked_api_key, model com $hidden), chave em branco no editar = manter,
 * anti-SSRF na base_url, teste de conexão REAL e auditoria sem segredo.
 */
class AiConfigurationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function administrador(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'OpenAI Texto',
            'provider' => 'openai',
            'capability' => 'text',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-chave-1234',
            'model' => 'gpt-5.4-mini',
            'temperature' => 0.1,
            'max_tokens' => 4096,
            'timeout_ms' => 60000,
            'active' => true,
            'is_default' => false,
        ], $overrides);
    }

    public function test_administrador_acessa_a_tela_de_configuracao(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/config-ia')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('gestao/config-ia/index'));
    }

    public function test_sem_a_permissao_o_acesso_e_negado_e_auditado(): void
    {
        $analista = $this->analista();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/config-ia')
            ->assertForbidden();

        // O 403 é auditado no ponto único (bootstrap/app.php) — não reimplementar.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
            'causer_id' => $analista->id,
        ]);
    }

    public function test_cadastra_e_define_padrao_desmarcando_a_anterior_da_mesma_capacidade(): void
    {
        $anterior = AiConfiguration::factory()->create(['capability' => 'text', 'is_default' => true]);

        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/config-ia', $this->payload(['is_default' => true, 'api_key' => 'sk-nova-chave-1234']))
            ->assertRedirect();

        $nova = AiConfiguration::query()->where('name', 'OpenAI Texto')->firstOrFail();

        $this->assertTrue($nova->is_default);
        $this->assertFalse($anterior->fresh()->is_default);
        $this->assertSame('sk-nova-chave-1234', $nova->api_key);
    }

    public function test_atualizar_com_chave_em_branco_mantem_a_chave_atual(): void
    {
        $config = AiConfiguration::factory()->create(['api_key' => 'sk-original-1234']);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/config-ia/{$config->id}", $this->payload([
                'name' => $config->name,
                'base_url' => $config->base_url,
                'api_key' => '',
            ]))
            ->assertRedirect();

        $this->assertSame('sk-original-1234', $config->fresh()->api_key);
    }

    public function test_a_listagem_usa_mascara_e_nunca_expoe_a_api_key_em_claro(): void
    {
        $config = AiConfiguration::factory()->create(['api_key' => 'sk-listagem-SECRETA-1234']);

        $response = $this->actingAs($this->administrador(), 'gestao')->get('/gestao/config-ia');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/config-ia/index')
                ->has('configurations', 1)
                ->where('configurations.0.masked_api_key', $config->masked_api_key)
                ->missing('configurations.0.api_key'));

        // A credencial em claro NUNCA aparece na resposta (props ou HTML).
        $response->assertDontSee('sk-listagem-SECRETA-1234', false);
    }

    public function test_o_model_oculta_a_api_key_na_serializacao(): void
    {
        $config = AiConfiguration::factory()->create(['api_key' => 'sk-oculta-1234']);

        // Defesa em profundidade ($hidden): toArray()/toJson() nunca devolvem a chave.
        $this->assertArrayNotHasKey('api_key', $config->toArray());
        $this->assertArrayNotHasKey('api_key', $config->fresh()->toArray());
    }

    public function test_testar_conexao_executa_chamada_real_e_audita(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['data' => []], 200)]);

        $config = AiConfiguration::factory()->create(['base_url' => 'https://api.openai.com/v1']);

        $this->actingAs($this->administrador(), 'gestao')
            ->post("/gestao/config-ia/{$config->id}/testar")
            ->assertRedirect();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/models'));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'config-ia',
            'event' => 'testar',
            'result' => 'sucesso',
        ]);
    }

    public function test_base_url_sem_https_e_rejeitada(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/config-ia', $this->payload(['base_url' => 'http://api.openai.com/v1']))
            ->assertSessionHasErrors('base_url');
    }

    public function test_base_url_fora_da_allowlist_e_rejeitada(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/config-ia', $this->payload(['base_url' => 'https://malicioso.example.com/v1']))
            ->assertSessionHasErrors('base_url');
    }

    public function test_excluir_remove_a_configuracao(): void
    {
        $config = AiConfiguration::factory()->create();

        $this->actingAs($this->administrador(), 'gestao')
            ->delete("/gestao/config-ia/{$config->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('ai_configurations', ['id' => $config->id]);
    }

    public function test_alterna_a_ativacao_da_configuracao(): void
    {
        $config = AiConfiguration::factory()->create(['active' => true]);

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/config-ia/{$config->id}/ativacao")
            ->assertRedirect();

        $this->assertFalse($config->fresh()->active);
    }
}
