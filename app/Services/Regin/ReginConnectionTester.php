<?php

namespace App\Services\Regin;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Teste de conexão REAL contra o /acesso/auth da URL ativa (homolog ou prod).
 * Falha é honesta: a mensagem leva o erro de conexão ou o corpo HTTP.
 * Senha e JWT nunca aparecem no resultado.
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
            $response = Http::timeout((int) config('viabiliza.integrations.regin.timeout', 8))
                ->acceptJson()
                ->asJson()
                ->post($this->settings->baseUrl().'/acesso/auth', [
                    'username' => $this->settings->username(),
                    'password' => $this->settings->password(),
                ]);
        } catch (ConnectionException) {
            return new ReginConnectionResult(
                false,
                "Não foi possível conectar ao ambiente de {$ambiente}. O serviço não respondeu a tempo. Tente novamente em instantes.",
            );
        }

        if ($response->successful() && filled($response->json('token'))) {
            return new ReginConnectionResult(true, "Conexão com a {$ambiente} bem-sucedida.");
        }

        $detalhe = $this->detalheHttp($response);

        if ($response->unauthorized() || $response->forbidden()) {
            return new ReginConnectionResult(false, $this->semSegredo("Credencial rejeitada pelo REGIN. {$detalhe}"));
        }

        return new ReginConnectionResult(
            false,
            $this->semSegredo("Falha ao autenticar no ambiente de {$ambiente}. {$detalhe}"),
        );
    }

    private function detalheHttp(Response $response): string
    {
        $corpo = trim($response->body());

        if (strlen($corpo) > 1500) {
            $corpo = substr($corpo, 0, 1500).'…';
        }

        $corpo = preg_replace('/("token"\s*:\s*")[^"]+/', '$1[redigido]', $corpo) ?? $corpo;

        return $corpo === ''
            ? 'HTTP '.$response->status()
            : 'HTTP '.$response->status().': '.$corpo;
    }

    private function semSegredo(string $texto): string
    {
        $senha = $this->settings->password();

        if ($senha === '') {
            return $texto;
        }

        return str_replace($senha, '[redigido]', $texto);
    }
}
