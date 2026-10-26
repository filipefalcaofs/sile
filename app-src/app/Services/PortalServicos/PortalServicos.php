<?php

namespace App\Services\PortalServicos;

use App\Support\Settings;

/**
 * Entrada do cidadão pelo Portal de Serviços da SEDUR (HU-142): disponibilidade
 * e endereços de ida e volta. O token da sessão do portal chega na entrada; sem
 * ele, o cidadão é mandado ao login do portal com a nossa entrada no `state`,
 * e o portal troca PREENCHER_TOKEN pelo token no retorno.
 */
class PortalServicos
{
    public const MARCADOR_TOKEN = 'PREENCHER_TOKEN';

    public static function entradaDisponivel(): bool
    {
        return Settings::enabled('login_portal_servicos')
            && filled(Settings::get('integrations.portal_servicos.bff_base_url'))
            && filled(Settings::get('integrations.portal_servicos.login_url'));
    }

    public static function urlLogin(string $voltarPara): string
    {
        return Settings::get('integrations.portal_servicos.login_url').'&state='.urlencode($voltarPara);
    }

    /**
     * Endereço da entrada com o marcador que o Portal de Serviços substitui
     * pelo token. Montado à mão para o marcador sair literal na query.
     */
    public static function urlEntrada(?string $destino = null, ?string $numero = null): string
    {
        $query = $destino !== null ? 'destino='.urlencode($destino).'&' : '';
        $query .= $numero !== null ? 'numero='.urlencode($numero).'&' : '';

        return url('/portal/entrar/portal-servicos').'?'.$query.'token='.self::MARCADOR_TOKEN;
    }

    public static function urlCarta(): string
    {
        return (string) Settings::get('integrations.portal_servicos.carta_url');
    }
}
