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
        return (bool) Settings::get('integrations.inscricao_imobiliaria.em_producao', true);
    }

    public function baseUrl(): string
    {
        $key = $this->usingProduction()
            ? 'integrations.inscricao_imobiliaria.url_producao'
            : 'integrations.inscricao_imobiliaria.url_homologacao';

        $fallback = $this->usingProduction()
            ? (string) config('sile.integrations.inscricao_imobiliaria.url_producao')
            : (string) config('sile.integrations.inscricao_imobiliaria.url_homologacao');

        return rtrim((string) Settings::get($key, $fallback), '/');
    }
}
