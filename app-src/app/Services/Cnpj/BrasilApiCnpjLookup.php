<?php

namespace App\Services\Cnpj;

use App\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Provider REAL de consulta de CNPJ contra a base aberta da Receita Federal
 * via BrasilAPI (formato idêntico ao do minhareceita.org — a URL é trocável
 * por parâmetro, sem deploy). Cache de 24h apenas de SUCESSO: falha nunca é
 * cacheada (a exceção dentro do closure impede a gravação). A Fase 13 troca
 * este binding pelo provider conveniado da RFB.
 */
class BrasilApiCnpjLookup implements CnpjLookup
{
    public function lookup(string $cnpj): CnpjData
    {
        $baseUrl = rtrim((string) Settings::get(
            'integrations.cnpj_lookup.base_url',
            config('sile.integrations.cnpj_lookup.base_url'),
        ), '/');

        return Cache::remember(
            "sile.cnpj_lookup.{$cnpj}",
            (int) config('sile.integrations.cnpj_lookup.cache_ttl', 86400),
            function () use ($baseUrl, $cnpj): CnpjData {
                try {
                    $response = Http::timeout((int) Settings::get(
                        'integrations.cnpj_lookup.timeout',
                        config('sile.integrations.cnpj_lookup.timeout', 8),
                    ))
                        ->connectTimeout(3)
                        ->retry(
                            (int) Settings::get(
                                'integrations.cnpj_lookup.retries',
                                config('sile.integrations.cnpj_lookup.retries', 2),
                            ),
                            (int) Settings::get(
                                'integrations.cnpj_lookup.backoff_ms',
                                config('sile.integrations.cnpj_lookup.backoff_ms', 200),
                            ),
                            throw: false,
                        )
                        ->acceptJson()
                        ->get("{$baseUrl}/{$cnpj}");
                } catch (ConnectionException $exception) {
                    throw new CnpjLookupException($cnpj);
                }

                if ($response->status() === 404) {
                    throw new CnpjNotFoundException($cnpj);
                }

                if ($response->failed()) {
                    throw new CnpjLookupException($cnpj, $response->status());
                }

                return CnpjData::fromBrasilApi($response->json());
            },
        );
    }
}
