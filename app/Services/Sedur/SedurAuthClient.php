<?php

namespace App\Services\Sedur;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP da API institucional SEDUR (ws-autenticacao + ws-gp). Segue o
 * padrão ReginHttpClient: senha e token nunca aparecem em exceção ou log, e a
 * verificação TLS é sempre exigida. O token Bearer é transitório — usado na
 * sequência auth → pessoaView da mesma request e descartado; nunca cacheado.
 *
 * Premissas de contrato a confirmar na homologação (escalonamento TI-SEDUR):
 * chave do token ("token") e chave do nome ("nome") — isoladas uma por método.
 */
class SedurAuthClient
{
    public function __construct(private SedurAuthSettings $settings) {}

    /**
     * Autentica a credencial corporativa e devolve o token Bearer transitório.
     *
     * @throws SedurAuthInvalidCredentialsException credencial rejeitada pela API
     * @throws SedurAuthUnavailableException API fora, erro HTTP ou contrato inválido
     */
    public function autenticar(string $usuario, string $senha): string
    {
        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->asJson()
                ->post($this->settings->baseUrl().'/ws-autenticacao/auth', [
                    'usuario' => $usuario,
                    'senha' => $senha,
                ]);
        } catch (ConnectionException) {
            throw new SedurAuthUnavailableException('API de autenticação institucional indisponível.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new SedurAuthInvalidCredentialsException('Credencial corporativa rejeitada.');
        }

        $token = $response->json('token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new SedurAuthUnavailableException('Falha ao autenticar na API institucional.');
        }

        return $token;
    }

    /**
     * Busca a pessoa pelo CPF e extrai SOMENTE o nome (minimização LGPD): o
     * restante do payload é descartado na mesma request, sem persistência.
     *
     * @return array{nome: ?string}
     *
     * @throws SedurAuthUnavailableException API fora ou erro HTTP
     */
    public function pessoaPorCpf(string $token, string $cpf): array
    {
        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->withToken($token)
                ->get($this->settings->baseUrl().'/ws-gp/view/pessoaView/buscarPorCPF/'.$cpf);
        } catch (ConnectionException) {
            throw new SedurAuthUnavailableException('API de gestão de pessoas indisponível.');
        }

        if (! $response->successful()) {
            throw new SedurAuthUnavailableException('Falha ao consultar dados da pessoa.');
        }

        $nome = $response->json('nome');

        return ['nome' => is_string($nome) && $nome !== '' ? $nome : null];
    }

    private function timeout(): int
    {
        return (int) config('sile.integrations.sedur_auth.timeout', 8);
    }
}
