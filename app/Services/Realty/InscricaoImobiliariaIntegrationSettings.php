<?php

namespace App\Services\Realty;

use App\Support\Settings;

/**
 * Lê a configuração administrável da API de inscrição imobiliária (HU-014).
 *
 * `em_producao` escolhe qual URL entra no cliente; as duas URLs continuam
 * editáveis no painel.
 */
class InscricaoImobiliariaIntegrationSettings
{
    public function usingProduction(): bool
    {
        return (bool) Settings::get('integrations.inscricao_imobiliaria.em_producao', false);
    }

    /**
     * A base pode terminar em `?valor=` (proxy-sefaz): a inscrição entra no fim,
     * como query. Sem query, entra como segmento do caminho (BFF).
     */
    public static function urlDaConsulta(string $base, string $inscricao): string
    {
        return str_contains($base, '?') ? $base.$inscricao : rtrim($base, '/').'/'.$inscricao;
    }

    public function baseUrl(): string
    {
        $key = $this->usingProduction()
            ? 'integrations.inscricao_imobiliaria.url_producao'
            : 'integrations.inscricao_imobiliaria.url_homologacao';

        $fallback = $this->usingProduction()
            ? (string) config('viabiliza.integrations.inscricao_imobiliaria.url_producao')
            : (string) config('viabiliza.integrations.inscricao_imobiliaria.url_homologacao');

        $url = (string) Settings::get($key, $fallback);

        return str_contains($url, '?') ? $url : rtrim($url, '/');
    }
}
