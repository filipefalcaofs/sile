<?php

namespace App\Services\Sefaz;

use App\Support\Settings;

/**
 * Ajustes da API do legado Simplifica, lidos dos parâmetros que a tela de APIs
 * grava (com o .env como valor de partida). Timeout e tempo de cache ficam só
 * em configuração.
 */
class LegadoSimplificaAjustes
{
    public function habilitada(): bool
    {
        return Settings::enabled('legado_simplifica');
    }

    public function url(): string
    {
        return rtrim((string) Settings::get('integrations.legado_simplifica.url'), '/');
    }

    public function token(): string
    {
        return (string) Settings::get('integrations.legado_simplifica.token');
    }

    public function configurada(): bool
    {
        return $this->url() !== '' && $this->token() !== '';
    }

    public function disponivel(): bool
    {
        return $this->habilitada() && $this->configurada();
    }

    public function timeout(): int
    {
        return (int) config('viabiliza.integrations.legado_simplifica.timeout', 6);
    }

    public function cacheTtl(): int
    {
        return (int) config('viabiliza.integrations.legado_simplifica.cache_ttl', 600);
    }
}
