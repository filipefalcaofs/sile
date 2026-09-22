<?php

namespace App\Services\Regin;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Teste de conexão REAL contra o /acesso/auth da URL ativa (homolog ou prod).
 * Falha é honesta; o resultado nunca carrega senha, JWT ou corpo da API.
 */
class ReginConnectionTester
{
    public function __construct(private ReginIntegrationSettings $settings) {}

    public function test(): ReginConnectionResult
    {
        $ambiente = $this->settings->usingProduction() ? 'produção' : 'homologação';

        if ($this->settings->username() === '' || $this->settings->password() === '') {
            return new ReginConnectionResult(
                false,
                'Informe usuário e senha da API REGIN antes de testar a conexão.',
            );
        }

        try {
            $response = Http::timeout((int) config('sile.integrations.regin.timeout', 8))
                ->acceptJson()
                ->asJson()
                ->post($this->settings->baseUrl().'/acesso/auth', [
                    'username' => $this->settings->username(),
                    'password' => $this->settings->password(),
                ]);
        } catch (ConnectionException) {
            return new ReginConnectionResult(
                false,
                "Ambiente de {$ambiente} indisponível. Confira a URL e a rede.",
            );
        }

        if ($response->successful() && filled($response->json('token'))) {
            return new ReginConnectionResult(true, "Conexão com a {$ambiente} bem-sucedida.");
        }

        if ($response->unauthorized() || $response->forbidden()) {
            return new ReginConnectionResult(false, 'Credencial rejeitada pelo REGIN.');
        }

        return new ReginConnectionResult(false, "Falha ao autenticar no ambiente de {$ambiente}.");
    }
}
