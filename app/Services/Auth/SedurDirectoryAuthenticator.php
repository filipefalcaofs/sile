<?php

namespace App\Services\Auth;

use App\Services\Sedur\SedurAuthClient;
use App\Services\Sedur\SedurAuthUnavailableException;

/**
 * DirectoryAuthenticator sobre a API institucional SEDUR. O token Bearer é
 * transitório: autentica, consulta discovery (lotação) e pessoaView do PRÓPRIO
 * usuário (minimização LGPD) e é descartado ao fim da chamada.
 */
class SedurDirectoryAuthenticator implements DirectoryAuthenticator
{
    public function __construct(private SedurAuthClient $client) {}

    public function attempt(string $adUsername, string $password, ?string $cpf = null): DirectoryAuthResult
    {
        $token = $this->client->autenticar($adUsername, $password);

        $setorCodigo = null;
        $setorNome = null;
        $sincronizacaoFalhou = false;

        try {
            $lotacao = $this->client->discovery($token);
            $setorCodigo = $lotacao['codigo'];
            $setorNome = $lotacao['nome'];
        } catch (SedurAuthUnavailableException) {
            $sincronizacaoFalhou = true;
        }

        if ($cpf === null || $cpf === '') {
            return new DirectoryAuthResult(
                nome: null,
                sincronizacaoFalhou: $sincronizacaoFalhou,
                setorCodigo: $setorCodigo,
                setorNome: $setorNome,
            );
        }

        try {
            $pessoa = $this->client->pessoaPorCpf($token, $cpf);
        } catch (SedurAuthUnavailableException) {
            return new DirectoryAuthResult(
                nome: null,
                sincronizacaoFalhou: true,
                setorCodigo: $setorCodigo,
                setorNome: $setorNome,
            );
        }

        return new DirectoryAuthResult(
            nome: $pessoa['nome'],
            sincronizacaoFalhou: $sincronizacaoFalhou,
            setorCodigo: $setorCodigo,
            setorNome: $setorNome,
        );
    }
}
