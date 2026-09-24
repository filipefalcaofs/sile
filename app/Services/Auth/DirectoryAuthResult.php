<?php

namespace App\Services\Auth;

/**
 * Resultado da validação no diretório: nome oficial (quando sincronizado) e
 * flag de falha da sincronização complementar — a identidade já estava
 * validada pelo /auth, então falha no pessoaView não derruba o login.
 */
final readonly class DirectoryAuthResult
{
    public function __construct(
        public ?string $nome,
        public bool $sincronizacaoFalhou = false,
    ) {}
}
