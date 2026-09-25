<?php

namespace App\Services\Sedur;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP da API institucional SEDUR (ws-autenticacao + ws-gp). Segue o
 * padrão ReginHttpClient: senha e token nunca aparecem em exceção ou log, e a
 * verificação TLS é sempre exigida. O token Bearer é transitório — usado na
 * sequência auth → discovery → pessoaView da mesma request e descartado; nunca cacheado.
 *
 * Premissas de contrato a confirmar na homologação (escalonamento TI-SEDUR):
 * chave do token ("token"), chave do nome ("nome") e chaves de lotação no
 * discovery (ver extractLotacao) — isoladas por método.
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
     * Consulta a lotação do próprio autenticado (GET discovery).
     *
     * @return array{codigo: ?string, nome: ?string}
     *
     * @throws SedurAuthUnavailableException API fora ou erro HTTP
     */
    public function discovery(string $token): array
    {
        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->withToken($token)
                ->get($this->settings->baseUrl().'/ws-autenticacao/discovery');
        } catch (ConnectionException) {
            throw new SedurAuthUnavailableException('API de discovery institucional indisponível.');
        }

        if (! $response->successful()) {
            throw new SedurAuthUnavailableException('Falha ao consultar discovery institucional.');
        }

        /** @var mixed $payload */
        $payload = $response->json();

        return $this->extractLotacao(is_array($payload) ? $payload : []);
    }

    /**
     * Extrai código/nome do setor da resposta do discovery.
     *
     * Premissa de homologação (formato real ainda não documentado pela TI-SEDUR):
     * tenta, nesta ordem, lotacao.{codigo|nome}, lotacao.{ceinNumero|ceinDescricao},
     * setor.{codigo|nome}, ceinNumero/ceinDescricao no root, codigoCentro/nomeCentro,
     * e por fim codigo+nome / setorNome no root.
     *
     * @param  array<string, mixed>  $payload
     * @return array{codigo: ?string, nome: ?string}
     */
    public function extractLotacao(array $payload): array
    {
        $candidatos = [];

        if (isset($payload['lotacao']) && is_array($payload['lotacao'])) {
            $candidatos[] = $payload['lotacao'];
        }

        if (isset($payload['setor']) && is_array($payload['setor'])) {
            $candidatos[] = $payload['setor'];
        }

        $candidatos[] = $payload;

        foreach ($candidatos as $bloco) {
            $codigo = $this->firstString($bloco, [
                'codigo',
                'code',
                'ceinNumero',
                'CEIN_NUMERO',
                'cein_numero',
                'codigoCentro',
                'codigo_centro',
            ]);
            $nome = $this->firstString($bloco, [
                'nome',
                'name',
                'descricao',
                'ceinDescricao',
                'CEIN_DESCRICAO',
                'cein_descricao',
                'nomeCentro',
                'nome_centro',
                'setorNome',
                'setor_nome',
            ]);

            if ($codigo !== null || $nome !== null) {
                return ['codigo' => $codigo, 'nome' => $nome];
            }
        }

        // lotacao/setor como string = só o nome
        foreach (['lotacao', 'setor'] as $chave) {
            if (isset($payload[$chave]) && is_string($payload[$chave]) && trim($payload[$chave]) !== '') {
                return ['codigo' => null, 'nome' => trim($payload[$chave])];
            }
        }

        return ['codigo' => null, 'nome' => null];
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

    /**
     * @param  array<string, mixed>  $bloco
     * @param  list<string>  $keys
     */
    private function firstString(array $bloco, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $bloco[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function timeout(): int
    {
        return (int) config('sile.integrations.sedur_auth.timeout', 8);
    }
}
