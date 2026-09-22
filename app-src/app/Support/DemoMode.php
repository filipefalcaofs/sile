<?php

namespace App\Support;

class DemoMode
{
    /**
     * Ambiente de demonstração para validação pela SEDUR (Portainer/staging).
     * Habilita massa de exemplo e credenciais de demo sem simular integrações.
     */
    public static function enabled(): bool
    {
        return filter_var(config('sile.demo_data', false), FILTER_VALIDATE_BOOL);
    }

    /**
     * Seeders de massa fictícia só rodam em dev/teste ou com demo_data ligado.
     */
    public static function allowsDemoSeeders(): bool
    {
        return app()->environment(['local', 'testing']) || self::enabled();
    }
}
