<?php

namespace App\Services\Renovacao;

final class RenovacaoElegibilidade
{
    public function __construct(
        public bool $elegivel,
        public ?string $motivo,
    ) {}
}
