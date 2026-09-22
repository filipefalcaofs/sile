<?php

namespace Tests\Unit\Services;

use App\Services\GovBr\GovBrAuthException;
use App\Services\GovBr\GovBrIdTokenValidator;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GovBrIdTokenValidatorTest extends TestCase
{
    private const BASE_URL = 'https://sso.staging.acesso.gov.br';

    private const CLIENT_ID = 'sile.salvador.ba.gov.br';

    /** @var resource|\OpenSSLAsymmetricKey */
    private $keyPair;

    private string $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($this->keyPair, $exported);
        $this->privateKey = $exported;

        Http::fake([
            self::BASE_URL.'/jwk' => Http::response($this->jwkSet($this->keyPair)),
        ]);
    }

    /**
     * @param  resource|\OpenSSLAsymmetricKey  $keyPair
     * @return array<string, mixed>
     */
    private function jwkSet($keyPair): array
    {
        $details = openssl_pkey_get_details($keyPair);

        $encode = fn (string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

        return [
            'keys' => [[
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => 'rsa1',
                'n' => $encode($details['rsa']['n']),
                'e' => $encode($details['rsa']['e']),
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function signedToken(array $overrides = [], ?string $privateKey = null): string
    {
        $claims = array_merge([
            'iss' => self::BASE_URL.'/',
            'aud' => self::CLIENT_ID,
            'sub' => '83368958004',
            'name' => 'Cidadão Teste',
            'email' => 'cidadao@example.com',
            'email_verified' => 'true',
            'nonce' => 'nonce-esperado',
            'iat' => time(),
            'exp' => time() + 3600,
        ], $overrides);

        return JWT::encode($claims, $privateKey ?? $this->privateKey, 'RS256', 'rsa1');
    }

    public function test_aceita_id_token_assinado_pela_chave_do_jwk(): void
    {
        $claims = (new GovBrIdTokenValidator)->validate(
            $this->signedToken(),
            self::CLIENT_ID,
            self::BASE_URL,
            'nonce-esperado',
        );

        $this->assertSame('83368958004', $claims['sub']);
        $this->assertSame('Cidadão Teste', $claims['name']);
    }

    public function test_rejeita_assinatura_de_outra_chave(): void
    {
        $otherKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($otherKey, $otherPrivateKey);

        $this->expectException(GovBrAuthException::class);

        (new GovBrIdTokenValidator)->validate(
            $this->signedToken([], $otherPrivateKey),
            self::CLIENT_ID,
            self::BASE_URL,
            'nonce-esperado',
        );
    }

    public function test_rejeita_token_expirado(): void
    {
        $this->expectException(GovBrAuthException::class);

        (new GovBrIdTokenValidator)->validate(
            $this->signedToken(['exp' => time() - 3600]),
            self::CLIENT_ID,
            self::BASE_URL,
            'nonce-esperado',
        );
    }

    public function test_rejeita_emissor_divergente(): void
    {
        $this->expectException(GovBrAuthException::class);

        (new GovBrIdTokenValidator)->validate(
            $this->signedToken(['iss' => 'https://sso.malicioso.example.com/']),
            self::CLIENT_ID,
            self::BASE_URL,
            'nonce-esperado',
        );
    }

    public function test_rejeita_audiencia_divergente(): void
    {
        $this->expectException(GovBrAuthException::class);

        (new GovBrIdTokenValidator)->validate(
            $this->signedToken(['aud' => 'outro-cliente']),
            self::CLIENT_ID,
            self::BASE_URL,
            'nonce-esperado',
        );
    }

    public function test_rejeita_nonce_divergente(): void
    {
        $this->expectException(GovBrAuthException::class);

        (new GovBrIdTokenValidator)->validate(
            $this->signedToken(['nonce' => 'nonce-forjado']),
            self::CLIENT_ID,
            self::BASE_URL,
            'nonce-esperado',
        );
    }

    public function test_iss_sem_barra_final_e_aceito(): void
    {
        $claims = (new GovBrIdTokenValidator)->validate(
            $this->signedToken(['iss' => self::BASE_URL]),
            self::CLIENT_ID,
            self::BASE_URL,
            'nonce-esperado',
        );

        $this->assertSame('83368958004', $claims['sub']);
    }
}
