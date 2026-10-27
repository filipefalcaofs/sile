<?php

namespace App\Services\Sefaz;

/**
 * Resultado sanitizado do teste de conexão com o legado Simplifica: só ok e
 * mensagem em português, nunca o corpo da API nem o token.
 */
final readonly class LegadoConexaoResultado
{
    public function __construct(
        public bool $ok,
        public string $mensagem,
    ) {}
}
