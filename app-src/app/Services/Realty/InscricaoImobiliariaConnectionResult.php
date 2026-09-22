<?php

namespace App\Services\Realty;

/**
 * Resultado sanitizado do teste de conexão da API de inscrição imobiliária.
 * Só ok + mensagem pt-BR. Nunca inclui corpo cru da API.
 */
class InscricaoImobiliariaConnectionResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $mensagem,
    ) {}
}
