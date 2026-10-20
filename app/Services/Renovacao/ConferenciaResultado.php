<?php

namespace App\Services\Renovacao;

/**
 * Resultado da conferência de titularidade; o motivo é texto para o cidadão
 * e nunca traz dados da outra parte.
 */
final readonly class ConferenciaResultado
{
    public function __construct(
        public bool $confere,
        public ?string $motivo = null,
    ) {}
}
