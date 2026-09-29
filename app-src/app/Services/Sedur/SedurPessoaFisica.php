<?php

namespace App\Services\Sedur;

final readonly class SedurPessoaFisica
{
    public function __construct(
        public string $cpf,
        public string $nome,
    ) {}
}
