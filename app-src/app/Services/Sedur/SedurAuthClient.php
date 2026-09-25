<?php

namespace App\Services\Sedur;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        return $this->sessao($usuario, $senha)['token'];
    }

    /**
     * Abre a sessão corporativa. O CPF vem no corpo do /auth e alimenta a
     * pessoaView; o token só segue para discovery e pessoaView nesta request.
     *
     * @return array{token: string, cpf: ?string}
     *
     * @throws SedurAuthInvalidCredentialsException credencial rejeitada pela API
     * @throws SedurAuthUnavailableException API fora, erro HTTP ou contrato inválido
     */
    public function sessao(string $usuario, string $senha): array
    {
        try {
            $response = $this->http()
                ->asJson()
                ->post($this->settings->baseUrl().'/ws-autenticacao/auth', [
                    'usuario' => $usuario,
                    'senha' => $senha,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('API de autenticação institucional indisponível.', [
                'motivo' => $exception->getMessage(),
            ]);

            throw new SedurAuthUnavailableException('API de autenticação institucional indisponível.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new SedurAuthInvalidCredentialsException('Credencial corporativa rejeitada.');
        }

        $token = $response->json('token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            Log::warning('Falha ao autenticar na API institucional.', [
                'status' => $response->status(),
            ]);

            throw new SedurAuthUnavailableException('Falha ao autenticar na API institucional.');
        }

        $cpf = preg_replace('/\D/', '', (string) $response->json('cpf'));

        return [
            'token' => $token,
            'cpf' => $cpf !== '' ? $cpf : null,
        ];
    }

    /**
     * Consulta a lotação do próprio autenticado (GET discovery).
     *
     * @return array{codigo: ?string, nome: ?string, email: ?string, telefone: ?string, nomePessoa: ?string}
     *
     * @throws SedurAuthUnavailableException API fora ou erro HTTP
     */
    public function discovery(string $token): array
    {
        try {
            $response = $this->http()
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

        $dados = is_array($payload) ? $payload : [];
        $lotacao = $this->extractLotacao($dados);

        return [
            'codigo' => $lotacao['codigo'],
            'nome' => $lotacao['nome'],
            'email' => $this->firstString($dados, ['email', 'mail', 'emailInstitucional', 'email_institucional']),
            'telefone' => $this->firstString($dados, ['telefone', 'phone', 'celular']),
            'nomePessoa' => $this->firstString($dados, ['nome', 'name', 'nomeCompleto', 'nome_completo']),
        ];
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
     * @return array{nome: ?string, email: ?string, telefone: ?string}
     *
     * @throws SedurAuthUnavailableException API fora ou erro HTTP
     */
    public function pessoaPorCpf(string $token, string $cpf): array
    {
        try {
            $response = $this->http()
                ->withToken($token)
                ->get($this->settings->baseUrl().'/ws-gp/view/pessoaView/buscarPorCPF/'.$cpf);
        } catch (ConnectionException) {
            throw new SedurAuthUnavailableException('API de gestão de pessoas indisponível.');
        }

        if (! $response->successful()) {
            throw new SedurAuthUnavailableException('Falha ao consultar dados da pessoa.');
        }

        /** @var mixed $payload */
        $payload = $response->json();
        $dados = is_array($payload) ? $payload : [];

        return [
            'nome' => $this->firstString($dados, ['nome', 'name', 'nomeCompleto', 'nome_completo']),
            'email' => $this->firstString($dados, ['email', 'mail', 'emailInstitucional', 'email_institucional']),
            'telefone' => $this->firstString($dados, ['telefone', 'phone', 'celular']),
        ];
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

    private function http(): PendingRequest
    {
        $request = Http::timeout($this->timeout())
            ->connectTimeout(10)
            ->acceptJson();

        $proxy = $this->proxy();

        if ($proxy !== null) {
            $request = $request->withOptions(['proxy' => $proxy]);
        }

        return $request;
    }

    /**
     * A rede da prefeitura sai por proxy. O cURL do build lê HTTPS_PROXY; o
     * cliente HTTP do PHP não. Sem isto a chamada ao AD falha só dentro do OKD.
     */
    private function proxy(): ?string
    {
        $proxy = getenv('https_proxy') ?: getenv('HTTPS_PROXY') ?: getenv('http_proxy') ?: getenv('HTTP_PROXY') ?: '';

        if (! is_string($proxy) || $proxy === '') {
            return null;
        }

        $host = parse_url($this->settings->baseUrl(), PHP_URL_HOST);
        $noProxy = getenv('no_proxy') ?: getenv('NO_PROXY') ?: '';

        foreach (explode(',', (string) $noProxy) as $entry) {
            $entry = trim($entry);

            if ($entry === '' || ! is_string($host)) {
                continue;
            }

            $suffix = ltrim($entry, '.');

            if ($entry === '*' || $host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return null;
            }
        }

        return $proxy;
    }

    private function timeout(): int
    {
        return (int) config('sile.integrations.sedur_auth.timeout', 30);
    }
}
