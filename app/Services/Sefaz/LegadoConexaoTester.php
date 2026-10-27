<?php

namespace App\Services\Sefaz;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Teste de conexão real com o legado Simplifica: consulta as provisórias de um
 * CNPJ técnico, que não existe. Resposta 200 (lista vazia) prova URL, token e
 * escopo `provisorias:ler`. A tela chama mesmo com a consulta desligada, para
 * conferir os dados antes de ligá-la.
 */
class LegadoConexaoTester
{
    private const CNPJ_TECNICO = '00000000000000';

    public function __construct(private LegadoSimplificaAjustes $ajustes) {}

    public function testar(): LegadoConexaoResultado
    {
        if (! $this->ajustes->configurada()) {
            return new LegadoConexaoResultado(false, 'Informe a URL da API e o token do legado antes de testar.');
        }

        try {
            $resposta = Http::withToken($this->ajustes->token())
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout($this->ajustes->timeout())
                ->get($this->ajustes->url().'/v1/provisorias', ['cnpj' => self::CNPJ_TECNICO]);
        } catch (ConnectionException) {
            return new LegadoConexaoResultado(false, 'API do legado indisponível. Confira a URL, o DNS e a rede.');
        }

        return match (true) {
            $resposta->successful() => new LegadoConexaoResultado(true, 'Conexão com o legado Simplifica bem-sucedida.'),
            $resposta->status() === 401 => new LegadoConexaoResultado(false, 'O legado recusou o token: ausente, inválido, revogado ou expirado.'),
            $resposta->status() === 403 => new LegadoConexaoResultado(false, 'O token não tem o escopo provisorias:ler.'),
            $resposta->status() === 429 => new LegadoConexaoResultado(false, 'O legado limitou as requisições. Tente de novo em instantes.'),
            default => new LegadoConexaoResultado(false, 'Falha ao consultar o legado (HTTP '.$resposta->status().').'),
        };
    }
}
