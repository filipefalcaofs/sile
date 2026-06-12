<?php

namespace App\Services\GovBr;

use App\Support\Settings;

/**
 * Disponibilidade do login GOV.BR (HU-151 RN-006): exige o toggle ligado E
 * credenciais configuradas — sem credenciamento a feature permanece
 * desligada, com degradação comunicada (nunca falha silenciosa).
 */
class GovBr
{
    public static function loginAvailable(): bool
    {
        return Settings::enabled('govbr_login')
            && filled(Settings::get('integrations.govbr.client_id'))
            && filled(Settings::get('integrations.govbr.client_secret'));
    }
}
