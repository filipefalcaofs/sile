<?php

namespace App\Services\Realty;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Teste de conexão REAL contra a URL ativa (homolog ou prod).
 * Usa uma inscrição técnica que não identifica imóvel; 2xx ou 404
 * comprovam que a API respondeu. Falha é honesta; o resultado nunca
 * carrega o corpo da API.
 */
class InscricaoImobiliariaConnectionTester
{
    public function __construct(private InscricaoImobiliariaIntegrationSettings $settings) {}

    public function test(): InscricaoImobiliariaConnectionResult
    {
        $ambiente = $this->settings->usingProduction() ? 'produção' : 'homologação';
        $inscricao = (string) config('sile.integrations.inscricao_imobiliaria.inscricao_teste', '0000000000');
        $url = $this->settings->baseUrl().'/'.$inscricao;

        try {
            $response = Http::timeout((int) config('sile.integrations.inscricao_imobiliaria.timeout', 12))
                ->connectTimeout(3)
                ->acceptJson()
                ->get($url);
        } catch (ConnectionException) {
            return new InscricaoImobiliariaConnectionResult(
                false,
                "Ambiente de {$ambiente} indisponível. Confira a URL e a rede.",
            );
        }

        if ($response->successful() || $response->notFound()) {
            return new InscricaoImobiliariaConnectionResult(
                true,
                "Conexão com a {$ambiente} bem-sucedida.",
            );
        }

        return new InscricaoImobiliariaConnectionResult(
            false,
            "Falha ao consultar o ambiente de {$ambiente}.",
        );
    }
}
