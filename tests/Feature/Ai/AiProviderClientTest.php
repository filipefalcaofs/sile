<?php

namespace Tests\Feature\Ai;

use App\Models\AiConfiguration;
use App\Services\Ai\AiProviderClient;
use App\Services\Ai\OpenAiCompatibleClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Teste de conexão REAL de um provedor de IA via HTTP OpenAI-compatible
 * (GET {base_url}/models com Authorization: Bearer). Cobre os requisitos de
 * segurança ALTOS da Onda 0: anti-SSRF (https + allowlist + sem redirect) e
 * sanitização do resultado (só { ok, mensagem categorizada } — nunca corpo,
 * headers, exceção crua ou a credencial).
 */
class AiProviderClientTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function config(array $overrides = []): AiConfiguration
    {
        return AiConfiguration::factory()->make($overrides);
    }

    public function test_o_binding_resolve_o_cliente_openai_compativel(): void
    {
        $this->assertInstanceOf(OpenAiCompatibleClient::class, app(AiProviderClient::class));
    }

    public function test_conexao_bem_sucedida_quando_o_provedor_responde_200(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['data' => []], 200)]);

        $config = $this->config(['base_url' => 'https://api.openai.com/v1', 'api_key' => 'sk-chave-valida-1234']);

        $result = app(AiProviderClient::class)->testConnection($config);

        $this->assertTrue($result->ok);
        $this->assertStringContainsStringIgnoringCase('sucesso', $result->mensagem);

        // Anti-fachada: chamada HTTP REAL ao endpoint {base_url}/models com
        // Authorization: Bearer {api_key} — sem chave válida, falha honesta.
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/models'
            && $request->hasHeader('Authorization', 'Bearer sk-chave-valida-1234'));
    }

    public function test_credencial_invalida_401_nao_vaza_o_corpo_nem_a_chave(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(
                ['error' => ['message' => 'Incorrect API key provided: sk-VAZAMENTO-PROIBIDO-99']],
                401,
            ),
        ]);

        $config = $this->config(['base_url' => 'https://api.openai.com/v1', 'api_key' => 'sk-chave-em-claro-NAO-VAZAR']);

        $result = app(AiProviderClient::class)->testConnection($config);

        $this->assertFalse($result->ok);
        $this->assertStringContainsStringIgnoringCase('credencial', $result->mensagem);
        // Sanitização: nunca o corpo cru da resposta nem chave alguma.
        $this->assertStringNotContainsString('sk-VAZAMENTO-PROIBIDO-99', $result->mensagem);
        $this->assertStringNotContainsString('Incorrect API key', $result->mensagem);
        $this->assertStringNotContainsString('sk-chave-em-claro-NAO-VAZAR', $result->mensagem);
    }

    public function test_timeout_devolve_falha_sanitizada_sem_propagar_excecao(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Connection timed out after 3000 ms'));

        $config = $this->config(['base_url' => 'https://api.openai.com/v1']);

        $result = app(AiProviderClient::class)->testConnection($config);

        $this->assertFalse($result->ok);
        // Mensagem categorizada, nunca a exceção crua.
        $this->assertStringNotContainsString('cURL error 28', $result->mensagem);
    }

    public function test_host_fora_da_allowlist_e_bloqueado_sem_chamada_http(): void
    {
        Http::fake();

        $config = $this->config(['base_url' => 'https://malicioso.example.com/v1']);

        $result = app(AiProviderClient::class)->testConnection($config);

        $this->assertFalse($result->ok);
        $this->assertStringContainsStringIgnoringCase('não autorizado', $result->mensagem);
        Http::assertNothingSent();
    }

    public function test_url_http_sem_tls_e_bloqueada_sem_chamada_http(): void
    {
        Http::fake();

        $config = $this->config(['base_url' => 'http://api.openai.com/v1']);

        $result = app(AiProviderClient::class)->testConnection($config);

        $this->assertFalse($result->ok);
        $this->assertStringContainsStringIgnoringCase('https', $result->mensagem);
        Http::assertNothingSent();
    }
}
