<?php

namespace App\Services\Realty;

use Exception;

/**
 * Inscrição imobiliária inexistente na base de lotes / Cadastro
 * Multifinalitário (o provider consultou a base oficial e não a localizou).
 */
class PropertyNotFoundException extends Exception
{
    public function __construct(public readonly string $inscricao)
    {
        parent::__construct("Inscrição imobiliária {$inscricao} não encontrada na base de lotes.");
    }
}
