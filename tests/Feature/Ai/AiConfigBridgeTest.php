<?php

namespace Tests\Feature\Ai;

use App\Models\AiConfiguration;
use App\Services\Ai\AiConfigResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\Ai\BridgeProbeAgent;
use Tests\TestCase;

/**
 * Ponte de runtime (Task 6) entre a configuração de IA ADMINISTRÁVEL (model
 * AiConfiguration, Onda 0) e o SDK laravel/ai em config('ai.*').
 *
 * As chaves aplicadas são as REAIS do SDK v0.8.x: ai.default (texto),
 * ai.default_for_embeddings, ai.providers.{nome}.driver|key|url|models.text.default
 * — NÃO api_key/base_url. A api_key é lida decriptada SÓ para a config em memória
 * (RN-009/LGPD): nunca é persistida em claro nem logada.
 */
class AiConfigBridgeTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): AiConfigResolver
    {
        return app(AiConfigResolver::class);
    }

    public function test_configuracao_padrao_de_texto_alimenta_o_sdk_em_config_ai(): void
    {
        AiConfiguration::factory()->create([
            'provider' => 'openai',
            'capability' => 'text',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-teste',
            'model' => 'gpt-4o-mini',
            'active' => true,
            'is_default' => true,
        ]);

        $this->resolver()->apply();

        $this->assertSame('openai', config('ai.default'));
        $this->assertSame('openai', config('ai.providers.openai.driver'));
        // Credencial decriptada SÓ na config em memória (nunca em claro no banco).
        $this->assertSame('sk-teste', config('ai.providers.openai.key'));
        $this->assertSame('gpt-4o-mini', config('ai.providers.openai.models.text.default'));
        $this->assertSame('https://api.openai.com/v1', config('ai.providers.openai.url'));
    }

    public function test_provider_compativel_mapeia_para_driver_openai_usando_a_base_url(): void
    {
        AiConfiguration::factory()->create([
            'provider' => 'compativel',
            'capability' => 'text',
            'base_url' => 'https://compat.example.com/v1',
            'api_key' => 'sk-compat',
            'model' => 'modelo-compat',
            'active' => true,
            'is_default' => true,
        ]);

        $this->resolver()->apply();

        $this->assertSame('compativel', config('ai.default'));
        $this->assertSame('openai', config('ai.providers.compativel.driver'));
        $this->assertSame('https://compat.example.com/v1', config('ai.providers.compativel.url'));
        $this->assertSame('sk-compat', config('ai.providers.compativel.key'));
        $this->assertSame('modelo-compat', config('ai.providers.compativel.models.text.default'));
    }

    public function test_sem_configuracao_ativa_a_ponte_nao_sobrescreve_os_defaults_do_vendor(): void
    {
        $resolved = $this->resolver()->resolve();

        $this->assertSame([], $resolved['defaults']);
        $this->assertSame([], $resolved['providers']);

        $this->resolver()->apply();

        // Defaults do pacote intactos: ai.default permanece e a credencial NÃO é
        // forjada (sem config ativa, openai.key segue nulo — degradação honesta).
        $this->assertSame('openai', config('ai.default'));
        $this->assertNull(config('ai.providers.openai.key'));
    }

    public function test_configuracao_inativa_nao_e_aplicada(): void
    {
        AiConfiguration::factory()->create([
            'provider' => 'openai',
            'capability' => 'text',
            'api_key' => 'sk-inativa',
            'model' => 'gpt-4o-mini',
            'active' => false,
            'is_default' => true,
        ]);

        $resolved = $this->resolver()->resolve();

        $this->assertSame([], $resolved['providers']);
        $this->assertNull(config('ai.providers.openai.key'));
    }

    public function test_o_sdk_opera_com_a_config_aplicada_via_fake_por_agent_sem_rede(): void
    {
        AiConfiguration::factory()->create([
            'provider' => 'openai',
            'capability' => 'text',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-teste',
            'model' => 'gpt-4o-mini',
            'active' => true,
            'is_default' => true,
        ]);

        $this->resolver()->apply();

        // Prova de ponta a ponta SEM credencial real: o fake por agent substitui
        // o gateway, mas o provedor 'openai' só é resolvível porque a ponte
        // aplicou driver/key/model em config('ai.providers.openai').
        BridgeProbeAgent::fake(['ok']);

        $response = BridgeProbeAgent::make()->prompt('oi');

        $this->assertSame('ok', $response->text);
    }

    public function test_alterar_a_configuracao_invalida_o_cache_e_a_ponte_reflete_o_novo_valor(): void
    {
        $config = AiConfiguration::factory()->create([
            'provider' => 'openai',
            'capability' => 'text',
            'api_key' => 'sk-teste',
            'model' => 'gpt-4o-mini',
            'active' => true,
            'is_default' => true,
        ]);

        $this->resolver()->apply();
        $this->assertSame('gpt-4o-mini', config('ai.providers.openai.models.text.default'));

        // A gravação dispara a invalidação do cache (evento do model); sem ela, a
        // re-resolução devolveria o valor cacheado antigo.
        $config->update(['model' => 'gpt-4o']);

        $this->resolver()->apply();
        $this->assertSame('gpt-4o', config('ai.providers.openai.models.text.default'));
    }
}
