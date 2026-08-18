<?php

namespace App\Services\Cnpj;

use Exception;

/**
 * CNPJ inexistente na base da Receita Federal (provider respondeu 404).
 */
class CnpjNotFoundException extends Exception
{
    public function __construct(public readonly string $cnpj)
    {
        parent::__construct("CNPJ {$cnpj} não encontrado na base da Receita Federal.");
    }
}
