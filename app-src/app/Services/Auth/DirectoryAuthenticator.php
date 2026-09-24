<?php

namespace App\Services\Auth;

use App\Services\Sedur\SedurAuthInvalidCredentialsException;
use App\Services\Sedur\SedurAuthUnavailableException;

/**
 * Validador de identidade corporativa (diretório institucional). O Laravel
 * permanece soberano sobre sessão, autorização e auditoria — este contrato
 * apenas responde "a credencial corporativa é válida?" e, quando há CPF,
 * traz o nome oficial para sincronização cadastral.
 */
interface DirectoryAuthenticator
{
    /**
     * @throws SedurAuthInvalidCredentialsException credencial rejeitada pelo diretório
     * @throws SedurAuthUnavailableException diretório indisponível
     */
    public function attempt(string $adUsername, string $password, ?string $cpf = null): DirectoryAuthResult;
}
