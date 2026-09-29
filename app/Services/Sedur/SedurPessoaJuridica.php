<?php

namespace App\Services\Sedur;

final readonly class SedurPessoaJuridica
{
    public function __construct(
        public string $cnpj,
        public string $razaoSocial,
        public ?string $nomeFantasia,
        public ?string $telefone,
        public ?string $endereco,
    ) {}
}
