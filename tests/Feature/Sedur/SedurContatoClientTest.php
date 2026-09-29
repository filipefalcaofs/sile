<?php

namespace Tests\Feature\Sedur;

use App\Services\Sedur\SedurContatoClient;
use App\Services\Sedur\SedurContatoIndisponivelException;
use App\Services\Sedur\SedurContatoNaoEncontradoException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SedurContatoClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['sile.integrations.sedur_contato.base_url' => 'https://sedur.test/k8s/prd/ws-contato']);
    }

    public function test_consulta_cnpj_le_razao_social_na_raiz(): void
    {
        Http::fake([
            'https://sedur.test/k8s/prd/ws-contato/pessoajuridica/cnpj/11222333000181' => Http::response([
                'cnpj' => '11222333000181',
                'razaoSocial' => 'PREFEITURA MUNICIPAL DO SALVADOR',
                'nomeFantasia' => 'SEGOV',
                'telefone' => '7132000000',
                'endereco' => [
                    'logradouro' => 'Avenida Tancredo Neves',
                    'numero' => '100',
                    'bairro' => 'Caminho das Árvores',
                    'municipio' => 'Salvador',
                    'uf' => 'BA',
                ],
            ]),
        ]);

        $empresa = app(SedurContatoClient::class)->consultarCnpj('11222333000181');

        $this->assertSame('PREFEITURA MUNICIPAL DO SALVADOR', $empresa->razaoSocial);
        $this->assertSame('SEGOV', $empresa->nomeFantasia);
        $this->assertStringContainsString('Avenida Tancredo Neves', $empresa->endereco);
    }

    public function test_consulta_cnpj_aceita_resposta_dentro_de_data(): void
    {
        Http::fake([
            '*/pessoajuridica/cnpj/*' => Http::response([
                'data' => ['razaoSocial' => 'EMPRESA EMBRULHADA LTDA'],
            ]),
        ]);

        $empresa = app(SedurContatoClient::class)->consultarCnpj('11222333000181');

        $this->assertSame('EMPRESA EMBRULHADA LTDA', $empresa->razaoSocial);
    }

    public function test_cnpj_inexistente_quando_a_api_responde_404(): void
    {
        Http::fake(['*/pessoajuridica/cnpj/*' => Http::response([], 404)]);

        $this->expectException(SedurContatoNaoEncontradoException::class);

        app(SedurContatoClient::class)->consultarCnpj('11222333000181');
    }

    public function test_401_com_corpo_de_nao_encontrado_nao_e_falha_de_autorizacao(): void
    {
        Http::fake([
            '*/pessoajuridica/cnpj/*' => Http::response(['titulo' => '404 Not Found'], 401),
        ]);

        $this->expectException(SedurContatoNaoEncontradoException::class);

        app(SedurContatoClient::class)->consultarCnpj('11222333000181');
    }

    public function test_retenta_erro_de_servidor_e_aceita_o_sucesso_seguinte(): void
    {
        Http::fake([
            '*/pessoajuridica/cnpj/*' => Http::sequence()
                ->push(['erro' => 'indisponivel'], 503)
                ->push(['razaoSocial' => 'EMPRESA APOS RETENTATIVA'], 200),
        ]);

        $empresa = app(SedurContatoClient::class)->consultarCnpj('11222333000181');

        $this->assertSame('EMPRESA APOS RETENTATIVA', $empresa->razaoSocial);
    }

    public function test_consulta_cpf_na_rota_de_pessoa_fisica(): void
    {
        Http::fake([
            'https://sedur.test/k8s/prd/ws-contato/pessoafisica/cpf/52998224725' => Http::response([
                'nome' => 'Maria Silva',
                'dataNascimento' => '1990-01-01',
                'telefone' => '71999999999',
                'email' => 'maria@example.com',
            ]),
        ]);

        $pessoa = app(SedurContatoClient::class)->consultarCpf('529.982.247-25');

        $this->assertSame('52998224725', $pessoa->cpf);
        $this->assertSame('Maria Silva', $pessoa->nome);
    }

    public function test_consulta_cpf_le_nome_em_chave_alternativa_dentro_de_pessoa_fisica(): void
    {
        Http::fake([
            '*/pessoafisica/cpf/*' => Http::response([
                'pessoaFisica' => ['nomeCompleto' => 'João Souza'],
            ]),
        ]);

        $pessoa = app(SedurContatoClient::class)->consultarCpf('52998224725');

        $this->assertSame('João Souza', $pessoa->nome);
    }

    public function test_cpf_inexistente_quando_a_api_responde_404(): void
    {
        Http::fake(['*/pessoafisica/cpf/*' => Http::response([], 404)]);

        $this->expectException(SedurContatoNaoEncontradoException::class);

        app(SedurContatoClient::class)->consultarCpf('52998224725');
    }

    public function test_servico_indisponivel_depois_das_tentativas(): void
    {
        Http::fake([
            '*/pessoajuridica/cnpj/*' => Http::response(['erro' => 'indisponivel'], 503),
        ]);

        $this->expectException(SedurContatoIndisponivelException::class);

        app(SedurContatoClient::class)->consultarCnpj('11222333000181');
    }
}
