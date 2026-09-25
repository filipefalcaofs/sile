<?php

namespace App\Services\Auth;

/**
 * Resultado da validação no diretório: nome oficial (pessoaView), lotação
 * (discovery) e flag de falha da sincronização complementar — a identidade já
 * estava validada pelo /auth, então falha em discovery/pessoaView não derruba
 * o login.
 */
final readonly class DirectoryAuthResult
{
    public function __construct(
        public ?string $nome = null,
        public bool $sincronizacaoFalhou = false,
        public ?string $setorCodigo = null,
        public ?string $setorNome = null,
    ) {}
}
