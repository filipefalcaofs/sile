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

        config(['viabiliza.integrations.sedur_auth.base_url' => 'https://sedur.test/k8s/prd']);
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

    public function test_pessoa_por_cpf_retorna_cadastro_sem_dados_extras(): void
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
            'lotacaoCodigo' => null,
            'lotacaoNome' => null,
        ], $pessoa);
        $this->assertArrayNotHasKey('endereco', $pessoa);

        Http::assertSent(fn ($request) => $request->url() === 'https://sedur.test/k8s/prd/ws-gp/view/pessoaView/buscarPorCPF/12345678901'
            && $request->hasHeader('Authorization', 'Bearer tk-123'));
    }

    public function test_pessoa_por_cpf_extrai_o_centro_de_informacao_como_lotacao(): void
    {
        Http::fake(['*/pessoaView/buscarPorCPF/*' => Http::response([
            'nome' => 'Maria Analista',
            'email' => 'maria@salvador.ba.gov.br',
            'centroInformacao' => [
                'codigo' => '0231400300',
                'descricao' => 'SEDUR-DLI-CAP-ATIV-Subcoordenadoria de Atividade',
            ],
        ])]);

        $pessoa = $this->client()->pessoaPorCpf('tk-123', '12345678901');

        $this->assertSame('0231400300', $pessoa['lotacaoCodigo']);
        $this->assertSame('SEDUR-DLI-CAP-ATIV-Subcoordenadoria de Atividade', $pessoa['lotacaoNome']);
        $this->assertSame('maria@salvador.ba.gov.br', $pessoa['email']);
    }

    public function test_pessoa_por_cpf_le_o_formato_real_da_pessoa_view(): void
    {
        Http::fake(['*/pessoaView/buscarPorCPF/*' => Http::response([
            'nome' => 'Talita Analista',
            'cpf' => '12345678901',
            'dataNascimento' => '1990-01-01',
            'cargo' => 'Analista',
            'email' => 'talita@salvador.ba.gov.br',
            'ramal' => null,
            'centroInformacaoNumero' => '5914032500',
            'centroInformacaoDescricao' => 'SEDUR-DLI-CAP-ATIV-Convite',
        ])]);

        $pessoa = $this->client()->pessoaPorCpf('tk-123', '12345678901');

        $this->assertSame('5914032500', $pessoa['lotacaoCodigo']);
        $this->assertSame('SEDUR-DLI-CAP-ATIV-Convite', $pessoa['lotacaoNome']);
        $this->assertSame('Talita Analista', $pessoa['nome']);
    }

    public function test_pessoa_por_cpf_aceita_cein_no_root_e_em_lista(): void
    {
        $this->assertSame(
            ['codigo' => '8888888888', 'nome' => 'Setor Raiz'],
            $this->client()->extractLotacaoPessoa(['nome' => 'Pessoa', 'ceinNumero' => '8888888888', 'ceinDescricao' => 'Setor Raiz']),
        );

        $this->assertSame(
            ['codigo' => '7777777777', 'nome' => 'Setor Da Lista'],
            $this->client()->extractLotacaoPessoa(['nome' => 'Pessoa', 'lotacoes' => [['codigo' => '7777777777', 'descricao' => 'Setor Da Lista']]]),
        );

        $this->assertSame(
            ['codigo' => null, 'nome' => 'Setor Como Texto'],
            $this->client()->extractLotacaoPessoa(['nome' => 'Pessoa', 'centroInformacao' => 'Setor Como Texto']),
        );
    }

    public function test_pessoa_por_cpf_nao_confunde_o_nome_da_pessoa_com_setor(): void
    {
        $this->assertSame(
            ['codigo' => null, 'nome' => null],
            $this->client()->extractLotacaoPessoa(['nome' => 'Maria Analista', 'codigo' => '123', 'email' => 'x@y.z']),
        );
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
