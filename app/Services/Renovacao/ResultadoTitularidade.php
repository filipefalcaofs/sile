<?php

namespace App\Services\Renovacao;

/**
 * Resultado da conferência de titularidade de uma TVL legada: a empresa é a
 * titular, não é (divergência real) ou a consulta cadastral ficou indisponível.
 */
final readonly class ResultadoTitularidade
{
    private function __construct(
        public bool $titular,
        public bool $consultaIndisponivel,
        public ?string $motivo,
    ) {}

    public static function titular(): self
    {
        return new self(true, false, null);
    }

    public static function outroTitular(string $motivo): self
    {
        return new self(false, false, $motivo);
    }

    public static function consultaIndisponivel(string $motivo): self
    {
        return new self(false, true, $motivo);
    }
}
