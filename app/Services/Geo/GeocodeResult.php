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
     * Tipos de endereço do Nominatim (addresstype) que localizam a rua, o bairro
     * ou algo maior, não o imóvel.
     */
    private const NIVEIS_SEM_LOTE = [
        'road', 'neighbourhood', 'quarter', 'suburb', 'city_district', 'borough',
        'city', 'town', 'village', 'hamlet', 'municipality', 'county',
        'state_district', 'state', 'region', 'country',
    ];

    /**
     * @param  array<string, mixed>  $address
     * @param  ?string  $addressType  addresstype do Nominatim (road, house, suburb...); não faz parte do JSON do endpoint
     */
    public function __construct(
        public float $latitude,
        public float $longitude,
        public string $displayName,
        public ?float $confidence,
        public array $address,
        public ?string $addressType = null,
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
            addressType: isset($payload['addresstype']) ? (string) $payload['addresstype'] : null,
        );
    }

    /**
     * Reidrata o array gravado no cache ({@see toArray()}).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromCached(array $payload): self
    {
        $address = $payload['address'] ?? [];

        return new self(
            latitude: (float) ($payload['latitude'] ?? 0),
            longitude: (float) ($payload['longitude'] ?? 0),
            displayName: (string) ($payload['display_name'] ?? ''),
            confidence: isset($payload['confidence']) ? (float) $payload['confidence'] : null,
            address: is_array($address) ? $address : [],
            addressType: isset($payload['address_type']) ? (string) $payload['address_type'] : null,
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

    /**
     * Array gravado no cache: o contrato do endpoint mais o tipo do endereço,
     * que o endpoint não expõe. A chave address_type sempre existe (mesmo nula):
     * entrada de cache sem ela é de antes do tipo e deve ser refeita.
     *
     * @return array<string, mixed>
     */
    public function toCache(): array
    {
        return $this->toArray() + ['address_type' => $this->addressType];
    }

    /**
     * O Nominatim localizou a rua, o bairro ou algo maior (addresstype road,
     * suburb, city...), não o imóvel: o ponto vale como "está nesta rua", sem
     * dizer em qual trecho. Sem tipo conhecido, não se presume.
     */
    public function nivelDeLogradouroOuMaior(): bool
    {
        return $this->addressType !== null && in_array($this->addressType, self::NIVEIS_SEM_LOTE, true);
    }

    /**
     * O endereço resolvido está no município de Salvador. O nome do município
     * (Nominatim: city/town/municipality) vence; sem nome, vale a caixa
     * aproximada do município. Estado diferente da Bahia recusa na hora.
     */
    public function estaEmSalvador(): bool
    {
        $estado = $this->normalizar((string) ($this->address['state'] ?? ''));

        if ($estado !== '' && ! in_array($estado, ['bahia', 'ba'], true)) {
            return false;
        }

        $municipio = $this->normalizar((string) (
            $this->address['city']
            ?? $this->address['town']
            ?? $this->address['municipality']
            ?? ''
        ));

        if ($municipio !== '') {
            return $municipio === 'salvador';
        }

        return $this->latitude <= -12.73
            && $this->latitude >= -13.02
            && $this->longitude <= -38.30
            && $this->longitude >= -38.72;
    }

    private function normalizar(string $valor): string
    {
        $valor = mb_strtolower(trim($valor));

        return strtr($valor, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);
    }
}
