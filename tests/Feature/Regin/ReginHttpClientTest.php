<?php

namespace Tests\Feature\Regin;

use App\Models\Parameter;
use App\Services\Regin\ReginHttpClient;
use App\Services\Regin\ReginUnavailableException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReginHttpClientTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Parameter::factory()->create([
            'key' => 'integrations.regin.em_producao',
            'group' => 'integracoes',
            'type' => 'boolean',
            'value' => '0',
            'default_value' => '0',
        ]);
        Parameter::factory()->create([
            'key' => 'integrations.regin.url_homologacao',
            'group' => 'integracoes',
            'type' => 'string',
            'value' => 'http://10.57.247.9:8080/api_integracao',
            'default_value' => 'http://10.57.247.9:8080/api_integracao',
        ]);
        Parameter::factory()->create([
            'key' => 'integrations.regin.usuario',
            'group' => 'integracoes',
            'type' => 'string',
            'value' => 'sedur_integracao',
            'default_value' => 'sedur_integracao',
        ]);
        Parameter::factory()->sensitive()->create([
            'key' => 'integrations.regin.senha',
            'group' => 'integracoes',
            'type' => 'string',
            'value' => 'segredo-forte',
            'default_value' => null,
        ]);
    }

    public function test_token_autentica_em_acesso_auth(): void
    {
        Http::fake([
            '*/acesso/auth' => Http::response(['token' => 'jwt-123', 'username' => 'sedur_integracao']),
        ]);

        $token = app(ReginHttpClient::class)->token();

        $this->assertSame('jwt-123', $token);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/acesso/auth')
            && $request['username'] === 'sedur_integracao'
            && $request['password'] === 'segredo-forte');
    }

    public function test_enviar_parecer_usa_header_jwt_e_aceita_recebido_sucesso(): void
    {
        Http::fake([
            '*/acesso/auth' => Http::response(['token' => 'jwt-123']),
            '*/recebe' => Http::response('RECEBIDO_SUCESSO'),
        ]);

        app(ReginHttpClient::class)->enviarParecer(['protocolo' => '43747']);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/recebe')
            && $request->hasHeader('JWT', 'jwt-123')
            && ! $request->hasHeader('Authorization'));
    }

    public function test_enviar_parecer_lanca_quando_corpo_nao_e_recebido_sucesso(): void
    {
        Http::fake([
            '*/acesso/auth' => Http::response(['token' => 'jwt-123']),
            '*/recebe' => Http::response('ERRO_QUALQUER'),
        ]);

        $this->expectException(ReginUnavailableException::class);

        app(ReginHttpClient::class)->enviarParecer(['protocolo' => '43747']);
    }

    public function test_enviar_parecer_lanca_em_falha_http_e_timeout_sem_vazar_token(): void
    {
        Http::fake([
            '*/acesso/auth' => Http::response(['token' => 'jwt-123']),
            '*/recebe' => Http::response('erro', 500),
        ]);

        try {
            app(ReginHttpClient::class)->enviarParecer(['protocolo' => '43747']);
            $this->fail('Deveria lançar ReginUnavailableException');
        } catch (ReginUnavailableException $e) {
            $this->assertStringNotContainsString('jwt-123', $e->getMessage());
            $this->assertStringNotContainsString('segredo-forte', $e->getMessage());
        }

        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->expectException(ReginUnavailableException::class);
        app(ReginHttpClient::class)->enviarParecer(['protocolo' => '43747']);
    }

    public function test_token_sem_credencial_lanca(): void
    {
        Parameter::query()->where('key', 'integrations.regin.usuario')->update(['value' => '']);
        Parameter::query()->where('key', 'integrations.regin.senha')->update(['value' => '']);
        cache()->flush();

        $this->expectException(ReginUnavailableException::class);

        app(ReginHttpClient::class)->token();
    }
}
