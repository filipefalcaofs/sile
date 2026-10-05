<?php

namespace App\Services\Benchmark;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Microdados de tempo de abertura da REDESIM, por UF e competência.
 *
 * Origem: a API que alimenta o painel público de tempos de abertura
 * (`estatistica.redesim.gov.br`). Cada chamada devolve um XLSX com uma linha
 * por solicitação, incluindo as horas úteis da análise de viabilidade de
 * endereço — a parcela que cabe ao município e a única diretamente comparável
 * ao que o VIABILIZA mede. A série vai de 2019 em diante.
 *
 * A API NÃO é publicada como contrato estável. Por isso: interruptor próprio,
 * falha devolvida como valor (nunca exceção para o chamador) e evidência de
 * cada chamada registrada pela ingestão.
 */
class RedesimClient
{
    public function baixar(string $uf, int $ano, int $mes): RedesimDownload
    {
        $url = $this->url($uf);

        if (! Settings::enabled('benchmark_redesim')) {
            return RedesimDownload::falhou($url, 'Importação do benchmark da REDESIM desligada.');
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('sile.integrations.redesim_estatistica.user_agent', 'Viabiliza-SEDUR-Salvador/1.0'),
            ])
                // São Paulo baixa cerca de 16 MB por competência.
                ->timeout((int) config('sile.integrations.redesim_estatistica.timeout', 180))
                ->connectTimeout(15)
                ->get($url, ['ano' => $ano, 'mes' => $mes]);
        } catch (Throwable $e) {
            return RedesimDownload::falhou($url, 'Estatísticas da REDESIM indisponíveis: '.$e->getMessage());
        }

        if ($response->failed()) {
            return RedesimDownload::falhou($url, 'Estatísticas da REDESIM responderam HTTP '.$response->status().'.');
        }

        $conteudo = $response->body();

        if ($conteudo === '') {
            return RedesimDownload::falhou($url, 'Estatísticas da REDESIM devolveram resposta vazia.');
        }

        return RedesimDownload::recebido($url, $conteudo);
    }

    private function url(string $uf): string
    {
        $base = rtrim((string) config('sile.integrations.redesim_estatistica.base_url'), '/');

        return $base.'/exportar/solicitacoes/'.strtoupper(trim($uf));
    }
}
