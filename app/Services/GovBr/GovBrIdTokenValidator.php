<?php

namespace App\Services\GovBr;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Valida o id_token do Login Único conforme o roteiro oficial: assinatura
 * RS256 contra o JWK do provedor, expiração, emissor, audiência e nonce.
 *
 * O JWK é buscado em {base}/jwk e cacheado por constante técnica
 * (sile.integrations.govbr.jwk_cache_ttl) — chave única por base_url para
 * staging e produção não se contaminarem.
 */
class GovBrIdTokenValidator
{
    /**
     * @return array<string, mixed> Claims validados do id_token.
     *
     * @throws GovBrAuthException
     */
    public function validate(string $idToken, string $clientId, string $baseUrl, ?string $expectedNonce = null): array
    {
        $baseUrl = rtrim($baseUrl, '/');

        JWT::$leeway = (int) config('sile.integrations.govbr.jwt_leeway', 60);

        try {
            $decoded = JWT::decode($idToken, JWK::parseKeySet($this->jwkSet($baseUrl), 'RS256'));
        } catch (Throwable $exception) {
            throw new GovBrAuthException("id_token rejeitado: {$exception->getMessage()}");
        }

        /** @var array<string, mixed> $claims */
        $claims = json_decode(json_encode($decoded), true);

        if (rtrim((string) ($claims['iss'] ?? ''), '/') !== $baseUrl) {
            throw new GovBrAuthException('id_token com emissor (iss) divergente do provedor configurado.');
        }

        $audience = (array) ($claims['aud'] ?? []);

        if (! in_array($clientId, $audience, true)) {
            throw new GovBrAuthException('id_token com audiência (aud) divergente do client_id.');
        }

        if ($expectedNonce !== null && (($claims['nonce'] ?? null) !== $expectedNonce)) {
            throw new GovBrAuthException('id_token com nonce divergente do esperado na sessão.');
        }

        return $claims;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws GovBrAuthException
     */
    private function jwkSet(string $baseUrl): array
    {
        $cacheKey = 'sile.govbr.jwk.'.sha1($baseUrl);

        try {
            $jwk = Cache::remember(
                $cacheKey,
                (int) config('sile.integrations.govbr.jwk_cache_ttl', 3600),
                fn (): array => Http::timeout((int) config('sile.integrations.govbr.timeout', 8))
                    ->get("{$baseUrl}/jwk")
                    ->throw()
                    ->json(),
            );
        } catch (GovBrAuthException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new GovBrAuthException("Falha ao obter o JWK do Login Único: {$exception->getMessage()}");
        }

        if (! is_array($jwk) || empty($jwk['keys'])) {
            Cache::forget($cacheKey);

            throw new GovBrAuthException('JWK do Login Único vazio ou em formato inesperado.');
        }

        return $jwk;
    }
}
