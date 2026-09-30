<?php

namespace App\Services\Sedur;

final readonly class ConsultaCpfSedurResult
{
    public function __construct(
        public ?string $nome,
        public bool $cadastrado,
    ) {}

    /**
     * @return array{nome: string|null, cadastrado: bool}
     */
    public function toArray(): array
    {
        return [
            'nome' => $this->nome,
            'cadastrado' => $this->cadastrado,
        ];
    }
}
