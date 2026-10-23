<?php

namespace App\Services\Sefaz;

/**
 * Resultado da comparação dos CNAEs do pedido com os da viabilidade vinculada
 * ao CNPJ na SEFAZ. Os códigos ficam só em dígitos (formato do banco).
 */
final readonly class ComparacaoCnaesViabilidade
{
    /**
     * @param  list<string>  $incluidas  no pedido e fora da viabilidade
     * @param  list<string>  $excluidas  na viabilidade e fora do pedido
     * @param  list<string>  $mantidas  nos dois
     * @param  list<string>  $invalidas  códigos do pedido que não constam da lista oficial
     */
    public function __construct(
        public array $incluidas = [],
        public array $excluidas = [],
        public array $mantidas = [],
        public array $invalidas = [],
        public ?string $numeroViabilidade = null,
        public bool $semViabilidade = false,
    ) {}

    /**
     * @return array{incluidas: list<string>, excluidas: list<string>, mantidas: list<string>, invalidas: list<string>, numero_viabilidade: ?string, sem_viabilidade: bool}
     */
    public function toArray(): array
    {
        return [
            'incluidas' => $this->incluidas,
            'excluidas' => $this->excluidas,
            'mantidas' => $this->mantidas,
            'invalidas' => $this->invalidas,
            'numero_viabilidade' => $this->numeroViabilidade,
            'sem_viabilidade' => $this->semViabilidade,
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public static function fromArray(array $dados): self
    {
        return new self(
            incluidas: array_values($dados['incluidas'] ?? []),
            excluidas: array_values($dados['excluidas'] ?? []),
            mantidas: array_values($dados['mantidas'] ?? []),
            invalidas: array_values($dados['invalidas'] ?? []),
            numeroViabilidade: $dados['numero_viabilidade'] ?? null,
            semViabilidade: (bool) ($dados['sem_viabilidade'] ?? false),
        );
    }
}
