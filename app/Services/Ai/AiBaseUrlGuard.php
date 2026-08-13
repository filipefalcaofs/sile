<?php

namespace App\Services\Ai;

use App\Support\Settings;

/**
 * Guarda anti-SSRF da `base_url` administrável de IA (requisito ALTO da Onda 0).
 *
 * A base_url é parametrizável pelo administrador → é superfície de SSRF. O
 * controle PRIMÁRIO é uma ALLOWLIST de hosts (defesa em profundidade: validada
 * no FormRequest E re-checada no client antes de qualquer chamada). Regras:
 * (1) somente HTTPS; (2) host (case-insensitive) na allowlist; nada de IP cru,
 * http, ftp, file, etc. A allowlist é lida via Settings (fallback config/sile.php)
 * — deploy-controlada de propósito: evita SSRF por má configuração do painel.
 */
class AiBaseUrlGuard
{
    /**
     * Valida a URL base. Devolve `null` se aprovada, ou a mensagem de rejeição
     * (pt-BR, sanitizada) se reprovada — nunca ecoa segredo nem detalhe interno.
     */
    public static function validate(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return 'Informe a URL base do provedor.';
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme']) || ! isset($parts['host'])) {
            return 'URL base inválida.';
        }

        if (strtolower($parts['scheme']) !== 'https') {
            return 'A URL base deve usar HTTPS.';
        }

        if (! in_array(strtolower($parts['host']), self::allowedHosts(), true)) {
            return 'Host não autorizado para integração de IA.';
        }

        return null;
    }

    public static function isAllowed(?string $url): bool
    {
        return self::validate($url) === null;
    }

    /**
     * Hosts permitidos (sempre minúsculos). Default em config/sile.php com os
     * provedores oficiais; sobrescritível via Settings sem alterar call sites.
     *
     * @return list<string>
     */
    public static function allowedHosts(): array
    {
        $hosts = Settings::get('ai.allowed_hosts', []);

        if (! is_array($hosts)) {
            return [];
        }

        return array_values(array_map(
            fn ($host): string => strtolower((string) $host),
            $hosts,
        ));
    }
}
