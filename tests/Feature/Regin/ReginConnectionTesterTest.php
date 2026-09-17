<?php

namespace Tests\Feature\Regin;

use App\Models\Parameter;
use App\Services\Regin\ReginConnectionTester;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReginConnectionTesterTest extends TestCase
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
            'value' => 'senha-de-teste-nao-vazar',
            'default_value' => null,
        ]);
    }

    public function test_conexao_bem_sucedida_autentica_na_url_ativa(): void
    {
        Http::fake([
            'http://10.57.247.9:8080/api_integracao/acesso/auth' => Http::response([
                'token' => 'jwt-falso',
                'username' => 'sedur_integracao',
            ], 200),
        ]);

        $result = app(ReginConnectionTester::class)->test();

        $this->assertTrue($result->ok);
        $this->assertStringContainsStringIgnoringCase('homologação', $result->mensagem);
        $this->assertStringNotContainsString('senha-de-teste-nao-vazar', $result->mensagem);
        $this->assertStringNotContainsString('jwt-falso', $result->mensagem);

        Http::assertSent(fn ($request) => $request->url() === 'http://10.57.247.9:8080/api_integracao/acesso/auth'
            && $request['username'] === 'sedur_integracao'
            && $request['password'] === 'senha-de-teste-nao-vazar');
    }

    public function test_credencial_rejeitada_nao_vaza_o_corpo_nem_a_senha(): void
    {
        Http::fake([
            'http://10.57.247.9:8080/api_integracao/acesso/auth' => Http::response([
                'error' => 'senha-de-teste-nao-vazar invalida',
            ], 401),
        ]);

        $result = app(ReginConnectionTester::class)->test();

        $this->assertFalse($result->ok);
        $this->assertStringContainsStringIgnoringCase('credencial', $result->mensagem);
        $this->assertStringNotContainsString('senha-de-teste-nao-vazar', $result->mensagem);
    }

    public function test_timeout_devolve_falha_sanitizada(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Connection timed out after 8000 ms'));

        $result = app(ReginConnectionTester::class)->test();

        $this->assertFalse($result->ok);
        $this->assertStringContainsStringIgnoringCase('indisponível', $result->mensagem);
        $this->assertStringNotContainsString('cURL', $result->mensagem);
    }
}
