<?php

namespace App\Services\GovBr;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User;

/**
 * Provider Socialite do Login Único GOV.BR (roteiro oficial acesso.gov.br).
 *
 * Particularidades do provedor: PKCE S256 e nonce obrigatórios; token request
 * com Authorization Basic (client_id:client_secret) e body sem credenciais;
 * perfil do cidadão lido do id_token (assinatura validada contra o JWK) em
 * vez do endpoint userinfo — inclui reliability_info (níveis bronze/prata/ouro).
 */
class GovBrProvider extends AbstractProvider
{
    protected $usesPKCE = true;

    protected $scopeSeparator = ' ';

    protected $scopes = [
        'openid',
        'email',
        'profile',
        'govbr_confiabilidades',
        'govbr_confiabilidades_idtoken',
    ];

    private string $baseUrl;

    private GovBrIdTokenValidator $idTokenValidator;

    public function withBaseUrl(string $baseUrl): static
    {
        $this->baseUrl = rtrim($baseUrl, '/');

        return $this;
    }

    public function withIdTokenValidator(GovBrIdTokenValidator $validator): static
    {
        $this->idTokenValidator = $validator;

        return $this;
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase("{$this->baseUrl}/authorize", $state);
    }

    /**
     * @param  string|null  $state
     * @return array<string, string>
     */
    protected function getCodeFields($state = null): array
    {
        $fields = parent::getCodeFields($state);

        $this->request->session()->put('govbr_nonce', $nonce = Str::random(32));

        $fields['nonce'] = $nonce;

        return $fields;
    }

    protected function getTokenUrl(): string
    {
        return "{$this->baseUrl}/token";
    }

    /**
     * @param  string  $code
     * @return array<string, string>
     */
    protected function getTokenHeaders($code): array
    {
        return array_merge(parent::getTokenHeaders($code), [
            'Authorization' => 'Basic '.base64_encode("{$this->clientId}:{$this->clientSecret}"),
        ]);
    }

    /**
     * Roteiro: credenciais vão APENAS no header Basic, nunca no body.
     *
     * @param  string  $code
     * @return array<string, string>
     */
    protected function getTokenFields($code): array
    {
        return Arr::except(parent::getTokenFields($code), ['client_id', 'client_secret']);
    }

    public function user(): User
    {
        if ($this->user) {
            return $this->user;
        }

        if ($this->hasInvalidState()) {
            throw new InvalidStateException;
        }

        $response = $this->getAccessTokenResponse($this->getCode());

        $idToken = Arr::get($response, 'id_token');

        if (! is_string($idToken) || $idToken === '') {
            throw new GovBrAuthException('Resposta do token do Login Único veio sem id_token.');
        }

        $claims = $this->idTokenValidator->validate(
            $idToken,
            $this->clientId,
            $this->baseUrl,
            $this->request->session()->pull('govbr_nonce'),
        );

        return $this->userInstance($response, $claims);
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['sub'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
        ]);
    }

    /**
     * Fallback para userFromToken(): consulta o userinfo com o access_token.
     *
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get("{$this->baseUrl}/userinfo", [
            'headers' => ['Authorization' => "Bearer {$token}"],
        ]);

        return json_decode((string) $response->getBody(), true) ?? [];
    }
}
