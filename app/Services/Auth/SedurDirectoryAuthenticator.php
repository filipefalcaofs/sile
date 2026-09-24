<?php

namespace App\Services\Auth;

use App\Services\Sedur\SedurAuthClient;
use App\Services\Sedur\SedurAuthUnavailableException;

/**
 * DirectoryAuthenticator sobre a API institucional SEDUR. O token Bearer é
 * transitório: autentica, consulta a pessoa do PRÓPRIO usuário (minimização
 * LGPD) e é descartado ao fim da chamada.
 */
class SedurDirectoryAuthenticator implements DirectoryAuthenticator
{
    public function __construct(private SedurAuthClient $client) {}

    public function attempt(string $adUsername, string $password, ?string $cpf = null): DirectoryAuthResult
    {
        $token = $this->client->autenticar($adUsername, $password);

        if ($cpf === null || $cpf === '') {
            return new DirectoryAuthResult(nome: null);
        }

        try {
            $pessoa = $this->client->pessoaPorCpf($token, $cpf);
        } catch (SedurAuthUnavailableException) {
            return new DirectoryAuthResult(nome: null, sincronizacaoFalhou: true);
        }

        return new DirectoryAuthResult(nome: $pessoa['nome']);
    }
}
