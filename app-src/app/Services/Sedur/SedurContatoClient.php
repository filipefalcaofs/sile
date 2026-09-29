<?php

namespace App\Services\Sedur;

use App\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Consulta cadastral anônima da SEDUR (ws-contato). CNPJ e CPF vão só com
 * dígitos no path. Erro de servidor e timeout são retentados; 404 e o 401
 * que a API usa como "não encontrado" não são.
 */
class SedurContatoClient
{
    private const TENTATIVAS = 3;

    public function consultarCnpj(string $cnpj): SedurPessoaJuridica
    {
        $digitos = preg_replace('/\D/', '', $cnpj) ?? '';
        $url = rtrim($this->baseUrl(), '/').'/pessoajuridica/cnpj/'.$digitos;

        $response = $this->enviar($url);
        $corpo = $this->corpo($response);

        if ($this->naoEncontrado($response, $corpo)) {
            throw new SedurContatoNaoEncontradoException('CNPJ não encontrado na base da SEDUR.');
        }

        if (! $response->successful() || $corpo === []) {
            throw new SedurContatoIndisponivelException('Consulta cadastral da SEDUR indisponível.');
        }

        $registro = $this->desembrulhar($corpo);

        return new SedurPessoaJuridica(
            cnpj: $digitos,
            razaoSocial: (string) ($registro['razaoSocial'] ?? ''),
            nomeFantasia: $this->texto($registro['nomeFantasia'] ?? null),
            telefone: $this->texto($registro['telefone'] ?? null),
            endereco: $this->endereco($registro['endereco'] ?? $registro['enderecoReceita'] ?? null),
        );
    }

    private function enviar(string $url): Response
    {
        $ultima = null;

        for ($tentativa = 1; $tentativa <= self::TENTATIVAS; $tentativa++) {
            try {
                $response = Http::timeout($this->timeout())
                    ->connectTimeout(4)
                    ->acceptJson()
                    ->get($url);
            } catch (ConnectionException) {
                if ($tentativa === self::TENTATIVAS) {
                    throw new SedurContatoIndisponivelException('Consulta cadastral da SEDUR indisponível.');
                }

                continue;
            }

            if (! $this->retentavel($response->status()) || $tentativa === self::TENTATIVAS) {
                return $response;
            }

            $ultima = $response;
        }

        return $ultima ?? throw new SedurContatoIndisponivelException('Consulta cadastral da SEDUR indisponível.');
    }

    private function retentavel(int $status): bool
    {
        return $status >= 500 || in_array($status, [408, 429, 504], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function corpo(Response $response): array
    {
        $json = $response->json();

        if (! is_array($json) || $json === [] || array_is_list($json)) {
            return [];
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $corpo
     */
    private function naoEncontrado(Response $response, array $corpo): bool
    {
        if ($response->status() === 404 || ($corpo === [] && in_array($response->status(), [200, 404], true))) {
            return true;
        }

        if (! in_array($response->status(), [401, 403], true)) {
            return false;
        }

        $texto = strtolower(json_encode($corpo, JSON_UNESCAPED_UNICODE) ?: '');

        return str_contains($texto, '404 not found')
            || str_contains($texto, 'não localizado')
            || str_contains($texto, 'nao localizado')
            || str_contains($texto, 'not found');
    }

    /**
     * @param  array<string, mixed>  $corpo
     * @return array<string, mixed>
     */
    private function desembrulhar(array $corpo): array
    {
        foreach (['data', 'result', 'pessoaJuridica', 'empresa'] as $chave) {
            if (isset($corpo[$chave]) && is_array($corpo[$chave]) && ! array_is_list($corpo[$chave])) {
                return $corpo[$chave];
            }
        }

        return $corpo;
    }

    private function endereco(mixed $endereco): ?string
    {
        if (is_string($endereco) && trim($endereco) !== '') {
            return trim($endereco);
        }

        if (! is_array($endereco)) {
            return null;
        }

        $partes = array_filter([
            $endereco['logradouro'] ?? null,
            $endereco['numero'] ?? $endereco['número'] ?? null,
            $endereco['bairro'] ?? null,
            $endereco['municipio'] ?? $endereco['cidade'] ?? null,
            $endereco['uf'] ?? null,
            $endereco['cep'] ?? null,
        ], fn (mixed $parte): bool => is_string($parte) && trim($parte) !== '');

        return $partes === [] ? null : implode(', ', $partes);
    }

    private function texto(mixed $valor): ?string
    {
        if (! is_string($valor) || trim($valor) === '' || trim($valor) === '...') {
            return null;
        }

        return trim($valor);
    }

    private function baseUrl(): string
    {
        return (string) Settings::get(
            'integrations.sedur_contato.base_url',
            config('sile.integrations.sedur_contato.base_url'),
        );
    }

    private function timeout(): int
    {
        return (int) config('sile.integrations.sedur_contato.timeout', 8);
    }
}
