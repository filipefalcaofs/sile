<?php

namespace App\Services\Regin;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP da API REGIN/JUCEB (api_integracao). Autentica em /acesso/auth
 * e usa o header JWT (não Authorization: Bearer) nas demais chamadas. Token e
 * senha nunca aparecem em exceção ou log. Sucesso do envio do parecer é o corpo
 * exato RECEBIDO_SUCESSO; qualquer outro resultado é indisponibilidade honesta.
 */
class ReginHttpClient
{
    public function __construct(private ReginIntegrationSettings $settings) {}

    public function token(): string
    {
        if ($this->settings->username() === '' || $this->settings->password() === '') {
            throw new ReginUnavailableException(motivo: 'Credenciais da API REGIN não configuradas.');
        }

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->asJson()
                ->post($this->settings->baseUrl().'/acesso/auth', [
                    'username' => $this->settings->username(),
                    'password' => $this->settings->password(),
                ]);
        } catch (ConnectionException) {
            throw new ReginUnavailableException(motivo: 'REGIN indisponível ao autenticar.');
        }

        $token = $response->json('token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new ReginUnavailableException(motivo: 'Falha ao autenticar no REGIN.');
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $resposta
     */
    public function enviarParecer(array $resposta): void
    {
        $token = $this->token();

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->asJson()
                ->withHeaders(['JWT' => $token])
                ->post($this->settings->baseUrl().'/recebe', $resposta);
        } catch (ConnectionException) {
            throw new ReginUnavailableException(motivo: 'REGIN indisponível ao enviar o parecer.');
        }

        if (! $response->successful() || trim($response->body()) !== 'RECEBIDO_SUCESSO') {
            throw new ReginUnavailableException(motivo: 'REGIN não confirmou o recebimento do parecer.');
        }
    }

    /**
     * @param  array<string, mixed>  $resposta
     */
    public function homologar(array $resposta): void
    {
        $this->postarComJwt('/teste/validaResposta', $resposta);
        $this->postarComJwt('/teste/testeRecebimento', $resposta);
    }

    /**
     * @param  array<string, mixed>  $corpo
     */
    private function postarComJwt(string $caminho, array $corpo): void
    {
        $token = $this->token();

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->asJson()
                ->withHeaders(['JWT' => $token])
                ->post($this->settings->baseUrl().$caminho, $corpo);
        } catch (ConnectionException) {
            throw new ReginUnavailableException(motivo: "REGIN indisponível em {$caminho}.");
        }

        if (! $response->successful()) {
            throw new ReginUnavailableException(motivo: "REGIN rejeitou a homologação em {$caminho}.");
        }
    }

    private function timeout(): int
    {
        return (int) config('sile.integrations.regin.timeout', 8);
    }
}
