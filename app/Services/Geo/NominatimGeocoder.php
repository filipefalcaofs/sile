<?php

namespace App\Services\Geo;

use App\Support\Settings;
use Closure;
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
            config('viabiliza.integrations.geocoding.base_url'),
        ), '/');

        $key = 'viabiliza.geocoding.'.sha1($address);
        $ttl = (int) config('viabiliza.integrations.geocoding.cache_ttl', 86400);
        $buscar = function () use ($baseUrl, $address): array {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => (string) config('viabiliza.integrations.geocoding.user_agent'),
                ])
                    ->timeout((int) Settings::get(
                        'integrations.geocoding.timeout',
                        config('viabiliza.integrations.geocoding.timeout', 8),
                    ))
                    ->connectTimeout(3)
                    ->retry(
                        (int) Settings::get(
                            'integrations.geocoding.retries',
                            config('viabiliza.integrations.geocoding.retries', 2),
                        ),
                        (int) Settings::get(
                            'integrations.geocoding.backoff_ms',
                            config('viabiliza.integrations.geocoding.backoff_ms', 1000),
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

            return GeocodeResult::fromNominatim($payload[0])->toArray();
        };

        return GeocodeResult::fromCached($this->lembrar($key, $ttl, $buscar));
    }

    /**
     * O cache guarda array. Entrada antiga (objeto) é descartada. Falha do
     * Nominatim não é cacheada.
     *
     * @param  Closure(): array<string, mixed>  $buscar
     * @return array<string, mixed>
     */
    private function lembrar(string $key, int $ttl, Closure $buscar): array
    {
        try {
            $payload = Cache::remember($key, $ttl, $buscar);
        } catch (AddressNotFoundException|GeocoderException $exception) {
            throw $exception;
        } catch (\Throwable) {
            Cache::forget($key);
            $payload = Cache::remember($key, $ttl, $buscar);
        }

        if (! is_array($payload)) {
            Cache::forget($key);
            $payload = Cache::remember($key, $ttl, $buscar);
        }

        return $payload;
    }
}
