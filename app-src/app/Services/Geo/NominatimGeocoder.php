<?php

namespace App\Services\Geo;

use App\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Provider REAL de geocodificação contra o Nominatim/OSM (base_url trocável por
 * parâmetro, sem deploy — self-host ou base SEDUR na Fase 13). Política do
 * Nominatim: User-Agent identificável obrigatório (libs HTTP genéricas são
 * bloqueadas), ≤ ~1 req/s (throttle parametrizado na rota) e cache obrigatório.
 *
 * Cache de 24h apenas de SUCESSO: a exceção dentro do closure impede a gravação
 * (falha nunca é cacheada — precedente [03-02]).
 */
class NominatimGeocoder implements Geocoder
{
    public function geocode(string $address): GeocodeResult
    {
        $baseUrl = rtrim((string) Settings::get(
            'integrations.geocoding.base_url',
            config('sile.integrations.geocoding.base_url'),
        ), '/');

        return Cache::remember(
            'sile.geocoding.'.sha1($address),
            (int) config('sile.integrations.geocoding.cache_ttl', 86400),
            function () use ($baseUrl, $address): GeocodeResult {
                try {
                    $response = Http::withHeaders([
                        'User-Agent' => (string) config('sile.integrations.geocoding.user_agent'),
                    ])
                        ->timeout((int) Settings::get(
                            'integrations.geocoding.timeout',
                            config('sile.integrations.geocoding.timeout', 8),
                        ))
                        ->connectTimeout(3)
                        ->retry(
                            (int) Settings::get(
                                'integrations.geocoding.retries',
                                config('sile.integrations.geocoding.retries', 2),
                            ),
                            (int) Settings::get(
                                'integrations.geocoding.backoff_ms',
                                config('sile.integrations.geocoding.backoff_ms', 1000),
                            ),
                            throw: false,
                        )
                        ->acceptJson()
                        ->get("{$baseUrl}/search", [
                            'q' => $address,
                            'format' => 'jsonv2',
                            'addressdetails' => 1,
                            'countrycodes' => 'br',
                            'limit' => 1,
                        ]);
                } catch (ConnectionException $exception) {
                    throw new GeocoderException($address);
                }

                if ($response->failed()) {
                    throw new GeocoderException($address, $response->status());
                }

                $payload = $response->json();

                // Corpo vazio ([]), HTML de bloqueio (não-array) ou objeto de
                // erro sem o primeiro resultado: endereço não localizado.
                if (! is_array($payload) || ! isset($payload[0]) || ! is_array($payload[0])) {
                    throw new AddressNotFoundException($address);
                }

                return GeocodeResult::fromNominatim($payload[0]);
            },
        );
    }
}
