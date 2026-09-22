<?php

namespace App\Services\Regin;

use App\Support\Settings;

/**
 * Lê a configuração administrável da API REGIN/JUCEB (HU-014).
 *
 * `em_producao` escolhe qual URL entra no cliente; as duas URLs continuam
 * editáveis no painel. Senha nunca é logada daqui.
 */
class ReginIntegrationSettings
{
    public function usingProduction(): bool
    {
        return (bool) Settings::get('integrations.regin.em_producao', false);
    }

    public function baseUrl(): string
    {
        $key = $this->usingProduction()
            ? 'integrations.regin.url_producao'
            : 'integrations.regin.url_homologacao';

        $fallback = $this->usingProduction()
            ? (string) config('sile.integrations.regin.url_producao')
            : (string) config('sile.integrations.regin.url_homologacao');

        return rtrim((string) Settings::get($key, $fallback), '/');
    }

    public function username(): string
    {
        return (string) Settings::get('integrations.regin.usuario', '');
    }

    public function password(): string
    {
        return (string) Settings::get('integrations.regin.senha', '');
    }
}
