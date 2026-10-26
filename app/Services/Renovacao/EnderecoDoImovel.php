<?php

namespace App\Services\Renovacao;

/** Endereço do imóvel conforme o cadastro imobiliário. */
final readonly class EnderecoDoImovel
{
    public function __construct(
        public ?string $logradouro,
        public ?string $numero,
        public ?string $bairro,
        public ?string $cep,
    ) {}

    /** Logradouro, número, bairro e CEP separados por vírgula, sem os campos vazios. */
    public function texto(): string
    {
        $partes = array_map(
            static fn (?string $parte): string => trim((string) $parte),
            [$this->logradouro, $this->numero, $this->bairro, $this->cep],
        );

        return implode(', ', array_filter($partes, static fn (string $parte): bool => $parte !== ''));
    }
}
