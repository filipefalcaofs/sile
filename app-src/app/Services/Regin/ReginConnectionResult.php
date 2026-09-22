<?php

namespace App\Services\Regin;

/**
 * Resultado sanitizado do teste de conexão REGIN — só ok + mensagem pt-BR.
 * Nunca inclui senha, token JWT ou corpo cru da API.
 */
class ReginConnectionResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $mensagem,
    ) {}
}
