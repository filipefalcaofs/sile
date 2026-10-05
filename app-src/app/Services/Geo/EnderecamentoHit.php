<?php

namespace App\Services\Geo;

/**
 * Resultado da consulta ao endereçamento SEDUR por CODLOG + número métrico.
 */
final readonly class EnderecamentoHit
{
    public const ENCONTRADO = 'encontrado';

    public const AMBIGUO = 'ambiguo';

    public const NAO_ENCONTRADO = 'nao_encontrado';

    public const INDISPONIVEL = 'indisponivel';

    public const DESLIGADO = 'desligado';

    /**
     * @param  array<string, mixed>|null  $geometria  GeoJSON Polygon/MultiPolygon em EPSG:4326
     * @param  array<string, mixed>|null  $propriedades
     */
    public function __construct(
        public string $status,
        public ?array $geometria = null,
        public ?array $propriedades = null,
        public int $candidatas = 0,
        public ?string $motivo = null,
    ) {}

    public function encontrado(): bool
    {
        return $this->status === self::ENCONTRADO;
    }
}
