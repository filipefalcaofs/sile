<?php

namespace App\Services\Geo;

use Exception;

/**
 * Endereço não localizado pelo provider de geocodificação (resposta vazia ou
 * não interpretável). O usuário ajusta o texto ou posiciona o ponto no mapa.
 */
class AddressNotFoundException extends Exception
{
    public function __construct(public readonly string $address)
    {
        parent::__construct("Endereço \"{$address}\" não localizado pelo provider de geocodificação.");
    }
}
