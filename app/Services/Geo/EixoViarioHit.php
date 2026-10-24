<?php

namespace App\Services\Geo;

/**
 * Segmentos do eixo de um logradouro, em linhas GeoJSON ([lng, lat] por
 * vértice), lidos da camada viária da SEDUR por CODLOG.
 */
final readonly class EixoViarioHit
{
    public const IDENTIFICADO = 'identificado';

    public const NAO_ENCONTRADO = 'nao_encontrado';

    public const INDISPONIVEL = 'indisponivel';

    public const DESLIGADO = 'desligado';

    /**
     * @param  list<list<array{0: float, 1: float}>>  $linhas
     */
    public function __construct(
        public string $status,
        public array $linhas = [],
        public ?string $motivo = null,
    ) {}

    public function identificado(): bool
    {
        return $this->status === self::IDENTIFICADO;
    }
}
