<?php

namespace App\Services\Geo;

/**
 * Resultado imutável da consulta ponto-em-polígono numa camada WFS do
 * GeoServer SEDUR (regras territoriais RT-02/03/04). Sem fachada:
 * `indisponivel` carrega o motivo (toggle desligado, falha HTTP, resposta
 * fora do contrato) — nunca um "fora" fingido.
 */
final readonly class GeoServerCamadaHit
{
    public const DENTRO = 'dentro';

    public const FORA = 'fora';

    public const INDISPONIVEL = 'indisponivel';

    /**
     * @param  array<string, mixed>|null  $properties
     */
    public function __construct(
        public string $status,
        public ?array $properties,
        public string $typeName,
        public ?string $motivo,
    ) {}

    public function dentro(): bool
    {
        return $this->status === self::DENTRO;
    }

    public function indisponivel(): bool
    {
        return $this->status === self::INDISPONIVEL;
    }
}
