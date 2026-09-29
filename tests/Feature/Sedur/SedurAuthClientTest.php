<?php

namespace Tests\Feature\Sedur;

use App\Services\Sedur\SedurAuthClient;
use App\Services\Sedur\SedurAuthInvalidCredentialsException;
use App\Services\Sedur\SedurAuthUnavailableException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SedurAuthClientTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sile.integrations.sedur_auth.base_url' => 'https://sedur.test/k8s/prd']);
    }

    private function client(): SedurAuthClient
    {
        return app(SedurAuthClient::class);
    }

    public function test_autenticar_retorna_o_token_em_sucesso(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response(['token' => 'tk-123'])]);

        $token = $this->client()->autenticar('seuusuarioad', 'senha-corporativa');

        $this->assertSame('tk-123', $token);

        Http::assertSent(fn ($request) => $request->url() === 'https://sedur.test/k8s/prd/ws-autenticacao/auth'
            && $request['usuario'] === 'seuusuarioad'
            && $request['senha'] === 'senha-corporativa');
    }

    public function test_credencial_rejeitada_lanca_excecao_especifica(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response(['erro' => 'negado'], 401)]);

        $this->expectException(SedurAuthInvalidCredentialsException::class);

        $this->client()->autenticar('seuusuarioad', 'senha-errada');
    }

    public function test_erro_http_da_api_e_indisponibilidade(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response('erro', 500)]);

        $this->expectException(SedurAuthUnavailableException::class);

        $this->client()->autenticar('seuusuarioad', 'senha-corporativa');
    }

    public function test_falha_de_conexao_e_indisponibilidade(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(SedurAuthUnavailableException::class);

        $this->client()->autenticar('seuusuarioad', 'senha-corporativa');
    }

    public function test_resposta_sem_token_e_indisponibilidade(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response(['ok' => true])]);

        $this->expectException(SedurAuthUnavailableException::class);

        $this->client()->autenticar('seuusuarioad', 'senha-corporativa');
    }

    public function test_senha_nunca_aparece_na_mensagem_das_excecoes(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response('senha-corporativa vazada no corpo', 500)]);

        try {
            $this->client()->autenticar('seuusuarioad', 'senha-corporativa');
            $this->fail('Esperava SedurAuthUnavailableException.');
        } catch (SedurAuthUnavailableException $e) {
            $this->assertStringNotContainsString('senha-corporativa', $e->getMessage());
        }
    }

    public function test_pessoa_por_cpf_retorna_apenas_o_nome(): void
    {
        Http::fake(['*/pessoaView/buscarPorCPF/*' => Http::response([
            'nome' => 'Filipe Falcao',
            'endereco' => 'Rua Exemplo, 1',
            'telefone' => '71999990000',
        ])]);

        $pessoa = $this->client()->pessoaPorCpf('tk-123', '12345678901');

        $this->assertSame([
            'nome' => 'Filipe Falcao',
            'email' => null,
            'telefone' => '71999990000',
        ], $pessoa);
        $this->assertArrayNotHasKey('endereco', $pessoa);

        Http::assertSent(fn ($request) => $request->url() === 'https://sedur.test/k8s/prd/ws-gp/view/pessoaView/buscarPorCPF/12345678901'
            && $request->hasHeader('Authorization', 'Bearer tk-123'));
    }

    public function test_pessoa_por_cpf_com_falha_e_indisponibilidade(): void
    {
        Http::fake(['*/pessoaView/buscarPorCPF/*' => Http::response('erro', 502)]);

        $this->expectException(SedurAuthUnavailableException::class);

        $this->client()->pessoaPorCpf('tk-123', '12345678901');
    }

    public function test_discovery_extrai_lotacao_aninhada(): void
    {
        Http::fake(['*/ws-autenticacao/discovery' => Http::response([
            'lotacao' => [
                'codigo' => '0231400300',
                'nome' => 'SUCOM-GERAE-STD-Setor de Triagem e Distribuição',
            ],
            'outros' => 'ignorado',
        ])]);

        $lotacao = $this->client()->discovery('tk-123');

        $this->assertSame('0231400300', $lotacao['codigo']);
        $this->assertSame('SUCOM-GERAE-STD-Setor de Triagem e Distribuição', $lotacao['nome']);

        Http::assertSent(fn ($request) => $request->url() === 'https://sedur.test/k8s/prd/ws-autenticacao/discovery'
            && $request->hasHeader('Authorization', 'Bearer tk-123'));
    }

    public function test_extract_lotacao_aceita_chaves_cein_no_root(): void
    {
        $lotacao = $this->client()->extractLotacao([
            'ceinNumero' => '8888888888',
            'ceinDescricao' => 'Setor Homologacao',
        ]);

        $this->assertSame([
            'codigo' => '8888888888',
            'nome' => 'Setor Homologacao',
        ], $lotacao);
    }

    public function test_discovery_com_falha_e_indisponibilidade(): void
    {
        Http::fake(['*/ws-autenticacao/discovery' => Http::response('erro', 502)]);

        $this->expectException(SedurAuthUnavailableException::class);

        $this->client()->discovery('tk-123');
    }
}
