<?php

namespace App\Services\Geo;

use RuntimeException;

class ForaDeSalvadorException extends RuntimeException
{
    public function __construct(public readonly string $address = '')
    {
        parent::__construct('O endereço está fora do município de Salvador. A consulta de viabilidade abrange apenas Salvador.');
    }
}
