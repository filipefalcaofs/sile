<?php

namespace App\Services\Sedur;

use App\Support\Settings;

/**
 * Configuração administrável da API institucional de autenticação AD / gestão
 * de pessoas da SEDUR (HU-014). A API não tem credencial de serviço: autentica
 * com a senha corporativa do próprio usuário, transitória — nada sensível aqui.
 */
class SedurAuthSettings
{
    public function baseUrl(): string
    {
        return rtrim((string) Settings::get(
            'integrations.sedur_auth.base_url',
            (string) config('sile.integrations.sedur_auth.base_url', ''),
        ), '/');
    }

    public function enabled(): bool
    {
        return Settings::enabled('ad_login');
    }
}
