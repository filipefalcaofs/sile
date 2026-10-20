<?php

namespace App\Services\Legado;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
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
     * @param  array<string, string>  $query
     */
    private function enviar(string $caminho, array $query = []): Response
    {
        $baseUrl = trim((string) config('viabiliza.integrations.legado_api.base_url'));
        $token = trim((string) config('viabiliza.integrations.legado_api.token'));

        if ($baseUrl === '' || $token === '') {
            throw new LegadoIndisponivelException('API legada de viabilidade não configurada.');
        }

        $base = rtrim($baseUrl, '/');
        $url = (str_ends_with($base, '/v1') ? $base : $base.'/v1').$caminho;
        $ultima = null;

        for ($tentativa = 1; $tentativa <= self::TENTATIVAS; $tentativa++) {
            try {
                $response = Http::timeout((int) config('viabiliza.integrations.legado_api.timeout', 8))
                    ->connectTimeout(4)
                    ->withToken($token)
                    ->acceptJson()
                    ->get($url, $query);
            } catch (ConnectionException) {
                if ($tentativa === self::TENTATIVAS) {
                    throw new LegadoIndisponivelException('API legada de viabilidade indisponível.');
                }

                continue;
            }

            if (! $this->retentavel($response->status()) || $tentativa === self::TENTATIVAS) {
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
