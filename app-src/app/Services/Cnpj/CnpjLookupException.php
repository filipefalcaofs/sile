<?php

namespace App\Services\Cnpj;

use Exception;

/**
 * Indisponibilidade ou erro do provider de consulta de CNPJ (5xx, timeout,
 * falha de conexão). Nunca é cacheada — o cadastro manual segue possível.
 */
class CnpjLookupException extends Exception
{
    public function __construct(
        public readonly string $cnpj,
        public readonly ?int $status = null,
    ) {
        parent::__construct(
            "Falha ao consultar o CNPJ {$cnpj} no provider de dados da Receita Federal.",
        );
    }
}
