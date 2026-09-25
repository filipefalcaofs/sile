<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\DirectoryAuthenticator;
use App\Services\Sedur\SedurAuthInvalidCredentialsException;
use App\Services\Sedur\SedurAuthUnavailableException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SedurDirectoryAuthenticatorTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sile.integrations.sedur_auth.base_url' => 'https://sedur.test/k8s/prd']);
    }

    private function authenticator(): DirectoryAuthenticator
    {
        return app(DirectoryAuthenticator::class);
    }

    public function test_sucesso_retorna_nome_sincronizado_da_pessoa(): void
    {
        Http::fake([
            '*/ws-autenticacao/auth' => Http::response(['token' => 'tk-1']),
            '*/ws-autenticacao/discovery' => Http::response([
                'lotacao' => ['codigo' => '0231400300', 'nome' => 'Triagem'],
            ]),
            '*/pessoaView/buscarPorCPF/*' => Http::response(['nome' => 'Filipe Falcao']),
        ]);

        $resultado = $this->authenticator()->attempt('filipefalcao', 'senha', '12345678901');

        $this->assertSame('Filipe Falcao', $resultado->nome);
        $this->assertSame('0231400300', $resultado->setorCodigo);
        $this->assertSame('Triagem', $resultado->setorNome);
        $this->assertFalse($resultado->sincronizacaoFalhou);
    }

    public function test_sem_cpf_nao_consulta_pessoa_view(): void
    {
        Http::fake([
            '*/ws-autenticacao/auth' => Http::response(['token' => 'tk-1']),
            '*/ws-autenticacao/discovery' => Http::response(['lotacao' => ['codigo' => '1', 'nome' => 'A']]),
        ]);

        $resultado = $this->authenticator()->attempt('filipefalcao', 'senha');

        $this->assertNull($resultado->nome);
        $this->assertSame('1', $resultado->setorCodigo);
        Http::assertSentCount(2);
    }

    public function test_credencial_invalida_propaga_excecao_especifica(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response([], 401)]);

        $this->expectException(SedurAuthInvalidCredentialsException::class);

        $this->authenticator()->attempt('filipefalcao', 'senha-errada', '12345678901');
    }

    public function test_indisponibilidade_no_auth_propaga_indisponibilidade(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response([], 500)]);

        $this->expectException(SedurAuthUnavailableException::class);

        $this->authenticator()->attempt('filipefalcao', 'senha', '12345678901');
    }

    public function test_falha_na_pessoa_view_nao_derruba_a_autenticacao(): void
    {
        Http::fake([
            '*/ws-autenticacao/auth' => Http::response(['token' => 'tk-1']),
            '*/ws-autenticacao/discovery' => Http::response(['lotacao' => ['codigo' => '1', 'nome' => 'A']]),
            '*/pessoaView/buscarPorCPF/*' => Http::response([], 502),
        ]);

        $resultado = $this->authenticator()->attempt('filipefalcao', 'senha', '12345678901');

        $this->assertNull($resultado->nome);
        $this->assertTrue($resultado->sincronizacaoFalhou);
        $this->assertSame('1', $resultado->setorCodigo);
    }

    public function test_falha_no_discovery_nao_derruba_a_autenticacao(): void
    {
        Http::fake([
            '*/ws-autenticacao/auth' => Http::response(['token' => 'tk-1']),
            '*/ws-autenticacao/discovery' => Http::response([], 502),
            '*/pessoaView/buscarPorCPF/*' => Http::response(['nome' => 'Filipe Falcao']),
        ]);

        $resultado = $this->authenticator()->attempt('filipefalcao', 'senha', '12345678901');

        $this->assertSame('Filipe Falcao', $resultado->nome);
        $this->assertTrue($resultado->sincronizacaoFalhou);
        $this->assertNull($resultado->setorCodigo);
    }
}
