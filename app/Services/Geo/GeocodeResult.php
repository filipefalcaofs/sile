<?php

namespace App\Services\Geo;

/**
 * DTO imutável do resultado da geocodificação. O named constructor
 * fromNominatim() mapeia o payload do Nominatim/OSM; toArray() expõe o contrato
 * snake_case consumido pelo endpoint JSON e pelo mapa (04-07).
 *
 * ATENÇÃO (Pitfall 3 — ordem de coordenadas): este DTO guarda latitude e
 * longitude NOMEADAS, sem ambiguidade. GeoJSON e ST_MakePoint usam a ordem
 * [longitude, latitude]; o Leaflet usa [latitude, longitude]. O consumidor é
 * responsável por respeitar a ordem do alvo ao montar ponto/geometria.
 */
final readonly class GeocodeResult
{
    /**
     * @param  array<string, mixed>  $address
     */
    public function __construct(
        public float $latitude,
        public float $longitude,
        public string $displayName,
        public ?float $confidence,
        public array $address,
    ) {}

    /**
     * Mapeia um resultado do Nominatim (jsonv2): lat/lon como string, importance
     * (0–1) como proxy de confiança e address detalhado.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromNominatim(array $payload): self
    {
        return new self(
            latitude: (float) ($payload['lat'] ?? 0),
            longitude: (float) ($payload['lon'] ?? 0),
            displayName: (string) ($payload['display_name'] ?? ''),
            confidence: isset($payload['importance']) ? (float) $payload['importance'] : null,
            address: (array) ($payload['address'] ?? []),
        );
    }

    /**
     * Contrato snake_case do JSON do endpoint e do mapa.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'display_name' => $this->displayName,
            'confidence' => $this->confidence,
            'address' => $this->address,
        ];
    }
}
