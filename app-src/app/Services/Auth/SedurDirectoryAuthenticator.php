<?php

namespace App\Services\Auth;

use App\Services\Sedur\SedurAuthClient;
use App\Services\Sedur\SedurAuthUnavailableException;

/**
 * DirectoryAuthenticator sobre a API institucional SEDUR. O token Bearer é
 * transitório: autentica, consulta discovery e pessoaView do PRÓPRIO usuário
 * (cadastro e centro de informação, que é a lotação no RH) e é descartado ao
 * fim da chamada.
 */
class SedurDirectoryAuthenticator implements DirectoryAuthenticator
{
    public function __construct(private SedurAuthClient $client) {}

    public function attempt(string $adUsername, string $password, ?string $cpf = null): DirectoryAuthResult
    {
        $sessao = $this->client->sessao($adUsername, $password);
        $token = $sessao['token'];
        $cpfConsulta = $sessao['cpf'] ?: $this->somenteDigitos($cpf);

        $setorCodigo = null;
        $setorNome = null;
        $email = null;
        $telefone = null;
        $nome = null;
        $sincronizacaoFalhou = false;

        try {
            $discovery = $this->client->discovery($token);
            $setorCodigo = $discovery['codigo'];
            $setorNome = $discovery['nome'];
            $email = $discovery['email'];
            $telefone = $discovery['telefone'];
            $nome = $discovery['nomePessoa'];
        } catch (SedurAuthUnavailableException) {
            $sincronizacaoFalhou = true;
        }

        if ($cpfConsulta === null) {
            return new DirectoryAuthResult(
                nome: $nome,
                sincronizacaoFalhou: $sincronizacaoFalhou,
                setorCodigo: $setorCodigo,
                setorNome: $setorNome,
                cpf: null,
                email: $email,
                telefone: $telefone,
            );
        }

        try {
            $pessoa = $this->client->pessoaPorCpf($token, $cpfConsulta);
            $nome = $pessoa['nome'] ?: $nome;
            $email = $pessoa['email'] ?: $email;
            $telefone = $pessoa['telefone'] ?: $telefone;

            // O discovery não traz a lotação; o centro de informação do RH vem
            // da pessoaView e completa o vínculo de setor.
            if ($setorCodigo === null && $setorNome === null) {
                $setorCodigo = $pessoa['lotacaoCodigo'];
                $setorNome = $pessoa['lotacaoNome'];
            }
        } catch (SedurAuthUnavailableException) {
            $sincronizacaoFalhou = true;
        }

        return new DirectoryAuthResult(
            nome: $nome,
            sincronizacaoFalhou: $sincronizacaoFalhou,
            setorCodigo: $setorCodigo,
            setorNome: $setorNome,
            cpf: $cpfConsulta,
            email: $email,
            telefone: $telefone,
        );
    }

    private function somenteDigitos(?string $valor): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $valor);

        return $digitos !== '' ? $digitos : null;
    }
}
