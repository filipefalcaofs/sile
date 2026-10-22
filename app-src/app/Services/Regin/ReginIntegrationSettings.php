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
            ? (string) config('viabiliza.integrations.regin.url_producao')
            : (string) config('viabiliza.integrations.regin.url_homologacao');

        return rtrim((string) Settings::get($key, $fallback), '/');
    }

    /**
     * Caminho, na API da JUCEB, que recebe o envelope Resposta (parecer). A
     * documentação pública não o nomeia; fica em parâmetro para a SEDUR trocar
     * sem deploy quando a JUCEB informar.
     */
    public function caminhoResposta(): string
    {
        $caminho = trim((string) Settings::get('integrations.regin.caminho_resposta', config('viabiliza.integrations.regin.caminho_resposta', '/recebe')));

        return '/'.ltrim($caminho, '/');
    }

    /**
     * CODIGO_AREA da análise na Resposta: a área/secretaria da instituição no
     * REGIN. A viabilidade de Salvador é a área 70 (JUCEB); o 1 do exemplo do
     * guia não corresponde a área nenhuma e deixa o protocolo pendente.
     */
    public function codigoArea(): int
    {
        return max(1, (int) Settings::get('integrations.regin.codigo_area', config('viabiliza.integrations.regin.codigo_area', 70)));
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
