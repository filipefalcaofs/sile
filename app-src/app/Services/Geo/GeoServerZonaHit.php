<?php

namespace App\Services\Geo;

/**
 * Resultado honesto da consulta WFS de zona no GeoServer SEDUR.
 * `indisponivel` = HTTP/rede falhou — nunca é tratado como "ponto fora da zona".
 */
final readonly class GeoServerZonaHit
{
    /**
     * @param  array<string, mixed>|null  $properties
     */
    public function __construct(
        public string $status,
        public ?string $codigo,
        public ?array $properties,
        public ?string $typeName,
        public ?string $motivo,
    ) {}
}
