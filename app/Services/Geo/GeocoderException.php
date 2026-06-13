<?php

namespace App\Services\Geo;

use Exception;

/**
 * Indisponibilidade ou erro do provider de geocodificação (5xx, timeout, falha
 * de conexão). Nunca é cacheada — a localização manual no mapa segue possível.
 */
class GeocoderException extends Exception
{
    public function __construct(
        public readonly string $address,
        public readonly ?int $status = null,
    ) {
        parent::__construct(
            "Falha ao geocodificar o endereço \"{$address}\" no provider de geocodificação.",
        );
    }
}
