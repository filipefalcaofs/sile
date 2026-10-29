<?php

namespace App\Services\Sefaz;

use App\Support\Settings;

/**
 * Ajustes da comparação de atividades com a viabilidade anterior no legado
 * Simplifica. A conexão (URL, token ou usuário e senha) é a da aba "Simplifica
 * (legado)" da tela de APIs, a mesma da consulta de processos; aqui só fica o
 * interruptor da comparação. Timeout e tempo de cache ficam só em configuração.
 */
class LegadoSimplificaAjustes
{
    public function habilitada(): bool
    {
        return Settings::enabled('legado_simplifica');
    }

    public function configurada(): bool
    {
        $url = trim((string) Settings::get('integrations.legado_api.base_url', config('viabiliza.integrations.legado_api.base_url')));
        $token = trim((string) Settings::get('integrations.legado_api.token', config('viabiliza.integrations.legado_api.token')));
        $acesso = trim((string) Settings::get('integrations.legado_api.usuario', '')) !== ''
            && (string) Settings::get('integrations.legado_api.senha', '') !== '';

        return $url !== '' && ($token !== '' || $acesso);
    }

    public function disponivel(): bool
    {
        return $this->habilitada() && $this->configurada();
    }

    public function cacheTtl(): int
    {
        return (int) config('viabiliza.integrations.legado_simplifica.cache_ttl', 600);
    }
}
