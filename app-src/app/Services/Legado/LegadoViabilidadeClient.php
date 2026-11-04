<?php

namespace App\Services\Legado;

use App\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cliente da API legada de viabilidade (somente leitura). Timeout e erro de
 * servidor são retentados; 404 vira null/lista vazia; 401, 403 e 422 e a
 * falta de configuração lançam LegadoIndisponivelException sem retentar.
 */
class LegadoViabilidadeClient
{
    private const TENTATIVAS = 3;

    /**
     * @return list<TvlLegada>
     */
    public function provisoriasPorCnpj(string $cnpj): array
    {
        $digitos = preg_replace('/\D/', '', $cnpj) ?? '';

        $response = $this->enviar('/provisorias', ['cnpj' => $digitos]);
        $this->validar($response);

        return array_map(
            fn (array $item): TvlLegada => TvlLegada::fromArray($item),
            $this->lista($response),
        );
    }

    /**
     * TVLs deferidas de qualquer tipo (provisória, definitiva, pré-operacional)
     * do CNPJ. API sem a rota /tvls (404) devolve só as provisórias.
     *
     * @return list<TvlLegada>
     */
    public function deferidasPorCnpj(string $cnpj): array
    {
        $digitos = preg_replace('/\D/', '', $cnpj) ?? '';

        $response = $this->enviar('/tvls', ['cnpj' => $digitos]);

        if ($response->status() === 404) {
            return $this->provisoriasPorCnpj($cnpj);
        }

        $this->validar($response);

        return array_map(
            fn (array $item): TvlLegada => TvlLegada::fromArray($item),
            $this->lista($response),
        );
    }

    public function tvlPorNumero(int $numero): ?TvlLegadaDetalhe
    {
        $data = $this->detalheBruto($numero);

        return $data === null ? null : TvlLegadaDetalhe::fromArray($data);
    }

    /**
     * @return list<TvlLegada>
     */
    public function tvlsDoProcesso(string $numeroProcesso): array
    {
        $response = $this->enviar('/processos', ['numero_processo' => $numeroProcesso]);

        if ($response->status() === 404) {
            return [];
        }

        $this->validar($response);

        $tvls = [];

        foreach ($this->lista($response) as $processo) {
            foreach ($processo['numeros_tvl'] ?? [] as $numero) {
                $data = $this->detalheBruto((int) $numero);

                if ($data !== null) {
                    $tvls[] = TvlLegada::fromArray($data);
                }
            }
        }

        return $tvls;
    }

    /**
     * Processos do sistema anterior de um CPF ou CNPJ (requerente ou CNPJ da
     * TVL), mais recentes primeiro.
     *
     * @return list<array<string, mixed>>
     */
    public function processosPorDocumento(string $documento): array
    {
        return $this->processosPorChave('documento', preg_replace('/\D/', '', $documento) ?? '');
    }

    /**
     * Lista de processos do sistema anterior por uma das chaves da API:
     * numero_processo, inscricao, documento ou numero_tvl.
     *
     * @return list<array<string, mixed>>
     */
    public function processosPorChave(string $chave, string $valor): array
    {
        $response = $this->enviar('/processos', [$chave => $valor]);

        if ($response->status() === 404) {
            return [];
        }

        $this->validar($response);

        return $this->lista($response);
    }

    /**
     * Pesquisa de processos do sistema anterior combinando os filtros da tela
     * (GET /processos/pesquisa). Os filtros se somam; a página vem com o total.
     *
     * @param  array<string, string>  $filtros
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, pagina: int, por_pagina: int, paginas: int}}
     */
    public function pesquisarProcessos(array $filtros, int $pagina = 1, int $porPagina = 15): array
    {
        $response = $this->enviar('/processos/pesquisa', $filtros + [
            'pagina' => (string) $pagina,
            'por_pagina' => (string) $porPagina,
        ], (int) config('viabiliza.integrations.legado_api.timeout_pesquisa', 30), 1);

        $this->validar($response);

        $meta = $response->json('meta');
        $meta = is_array($meta) ? $meta : [];
        $dados = $this->lista($response);

        return [
            'data' => $dados,
            'meta' => [
                'total' => (int) ($meta['total'] ?? count($dados)),
                'pagina' => (int) ($meta['pagina'] ?? $pagina),
                'por_pagina' => (int) ($meta['por_pagina'] ?? $porPagina),
                'paginas' => (int) ($meta['paginas'] ?? 1),
            ],
        ];
    }

    /**
     * Detalhe do processo do sistema anterior (número no formato
     * 0000000000-00000000/0000), com TVLs e histórico; null quando não existe.
     *
     * @return array<string, mixed>|null
     */
    public function processo(string $numero): ?array
    {
        $response = $this->enviar('/processos/'.$numero);

        if ($response->status() === 404) {
            return null;
        }

        $this->validar($response);

        $data = $response->json('data');

        return is_array($data) ? $data : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function detalheBruto(int $numero): ?array
    {
        $response = $this->enviar('/tvls/'.$numero);

        if ($response->status() === 404) {
            return null;
        }

        $this->validar($response);

        $data = $response->json('data');

        return is_array($data) ? $data : null;
    }

    /**
     * Consulta de leitura mínima para o teste de conexão da tela de APIs: 401,
     * 403 e erro de rede viram LegadoIndisponivelException; "não achou" é ok.
     *
     * @throws LegadoIndisponivelException
     */
    public function testarConexao(): void
    {
        $this->provisoriasPorCnpj('00000000000000');
        $this->processosPorChave('numero_tvl', '0');
    }

    /**
     * @param  array<string, string>  $query
     */
    private function enviar(string $caminho, array $query = [], ?int $timeout = null, int $tentativas = self::TENTATIVAS): Response
    {
        $base = $this->base();
        $url = $base.$caminho;
        $resposta = $this->tentar($url, $query, $this->credencial($base), $timeout, $tentativas);

        if ($resposta->status() === 401 && $this->usaSessao()) {
            Cache::forget($this->chaveDaSessao($base));
            $resposta = $this->tentar($url, $query, $this->credencial($base), $timeout, $tentativas);
        }

        return $resposta;
    }

    /**
     * Base já com /v1: a URL da tela (ou do .env) vale com ou sem o sufixo.
     */
    private function base(): string
    {
        $baseUrl = trim((string) Settings::get('integrations.legado_api.base_url', config('viabiliza.integrations.legado_api.base_url')));

        if ($baseUrl === '') {
            throw new LegadoIndisponivelException('API legada de viabilidade não configurada.');
        }

        $base = rtrim($baseUrl, '/');

        return str_ends_with($base, '/v1') ? $base : $base.'/v1';
    }

    private function token(): string
    {
        return trim((string) Settings::get('integrations.legado_api.token', config('viabiliza.integrations.legado_api.token')));
    }

    /**
     * Host enviado no cabeçalho quando a URL é um IP e o gateway roteia por nome.
     *
     * @return array<string, string>
     */
    private function cabecalhoHost(): array
    {
        $host = trim((string) Settings::get('integrations.legado_api.host', ''));

        return $host === '' ? [] : ['Host' => $host];
    }

    private function usuario(): string
    {
        return trim((string) Settings::get('integrations.legado_api.usuario', ''));
    }

    private function senha(): string
    {
        return (string) Settings::get('integrations.legado_api.senha', '');
    }

    private function usaSessao(): bool
    {
        return $this->token() === '' && $this->usuario() !== '' && $this->senha() !== '';
    }

    /**
     * Token fixo quando há um; senão a sessão aberta com usuário e senha
     * (guardada em cache, o token da API dura 8 horas).
     */
    private function credencial(string $base): string
    {
        $token = $this->token();

        if ($token !== '') {
            return $token;
        }

        if (! $this->usaSessao()) {
            throw new LegadoIndisponivelException('API legada de viabilidade sem token nem usuário e senha configurados.');
        }

        return Cache::remember($this->chaveDaSessao($base), now()->addHours(7), fn (): string => $this->entrar($base));
    }

    private function chaveDaSessao(string $base): string
    {
        return 'legado.sessao.'.hash('sha256', $base.'|'.$this->usuario().'|'.$this->senha());
    }

    private function entrar(string $base): string
    {
        try {
            $response = Http::timeout((int) config('viabiliza.integrations.legado_api.timeout', 8))
                ->connectTimeout(4)
                ->withHeaders($this->cabecalhoHost())
                ->acceptJson()
                ->post($base.'/auth', ['identificador' => $this->usuario(), 'senha' => $this->senha()]);
        } catch (ConnectionException) {
            throw new LegadoIndisponivelException('API legada de viabilidade indisponível.');
        }

        $this->validar($response);

        $token = $response->json('token');

        if (! is_string($token) || $token === '') {
            throw new LegadoIndisponivelException('API legada de viabilidade não devolveu o token de acesso.');
        }

        return $token;
    }

    /**
     * @param  array<string, string>  $query
     */
    private function tentar(string $url, array $query, string $token, ?int $timeout = null, int $tentativas = self::TENTATIVAS): Response
    {
        $ultima = null;
        $espera = $timeout ?? (int) config('viabiliza.integrations.legado_api.timeout', 8);

        for ($tentativa = 1; $tentativa <= $tentativas; $tentativa++) {
            try {
                $response = Http::timeout($espera)
                    ->connectTimeout(4)
                    ->withHeaders($this->cabecalhoHost())
                    ->withToken($token)
                    ->acceptJson()
                    ->get($url, $query);
            } catch (ConnectionException $e) {
                if ($tentativa === $tentativas) {
                    throw new LegadoIndisponivelException(
                        str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'cURL error 28')
                            ? "A API do legado demorou mais de {$espera} s para responder."
                            : 'API legada de viabilidade indisponível.',
                    );
                }

                continue;
            }

            if (! $this->retentavel($response->status()) || $tentativa === $tentativas) {
                return $response;
            }

            $ultima = $response;
        }

        return $ultima ?? throw new LegadoIndisponivelException('API legada de viabilidade indisponível.');
    }

    private function retentavel(int $status): bool
    {
        return $status >= 500 || in_array($status, [408, 429, 504], true);
    }

    private function validar(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $mensagem = $response->json('mensagem');

        throw new LegadoIndisponivelException(
            is_string($mensagem) && $mensagem !== ''
                ? $mensagem
                : 'API legada de viabilidade indisponível (HTTP '.$response->status().').',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lista(Response $response): array
    {
        $data = $response->json('data');

        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }
}
