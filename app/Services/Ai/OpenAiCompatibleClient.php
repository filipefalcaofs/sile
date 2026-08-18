<?php

namespace App\Services\Ai;

use App\Models\AiConfiguration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Teste de conexão REAL de um provedor de IA via API OpenAI-compatible:
 * GET {base_url}/models com Authorization: Bearer {api_key}. Sem SDK (a ponte
 * laravel/ai é da Onda 1) — esta verificação é HTTP direto e basta para a tela.
 *
 * Controles de segurança ALTOS (Onda 0):
 * - Anti-SSRF: a base_url administrável é re-checada por AiBaseUrlGuard (https +
 *   allowlist) ANTES de qualquer chamada (defesa em profundidade); sem seguir
 *   redirects (withoutRedirecting); connectTimeout baixo + timeout com teto.
 * - Sanitização: devolve SÓ ConnectionResult { ok, mensagem categorizada } —
 *   nunca o corpo/headers da resposta, a exceção crua ou a credencial. A
 *   api_key NUNCA é logada (este client não loga nada).
 */
class OpenAiCompatibleClient implements AiProviderClient
{
    public function testConnection(AiConfiguration $config): ConnectionResult
    {
        $baseUrl = (string) $config->base_url;

        // Defesa em profundidade: rejeita antes de qualquer egress.
        if (($rejection = AiBaseUrlGuard::validate($baseUrl)) !== null) {
            return ConnectionResult::falha($rejection);
        }

        $endpoint = rtrim($baseUrl, '/').'/models';

        try {
            $response = Http::withToken((string) $config->api_key)
                ->withoutRedirecting()
                ->acceptJson()
                ->connectTimeout((int) config('sile.ai.test.connect_timeout', 3))
                ->timeout($this->timeoutSeconds($config))
                ->get($endpoint);
        } catch (ConnectionException) {
            return ConnectionResult::falha('Tempo de conexão esgotado ou provedor inacessível.');
        } catch (Throwable) {
            // Qualquer outra falha de transporte: mensagem genérica, sem vazar o motivo cru.
            return ConnectionResult::falha('Não foi possível concluir o teste de conexão.');
        }

        if ($response->successful()) {
            return ConnectionResult::sucesso();
        }

        return ConnectionResult::falha($this->categorize($response->status()));
    }

    /**
     * Timeout total derivado de timeout_ms, com TETO máximo (anti-SSRF: um
     * timeout_ms enorme não pode prender o servidor). Mínimo de 1 segundo.
     */
    private function timeoutSeconds(AiConfiguration $config): int
    {
        $maxMs = (int) config('sile.ai.test.timeout_max_ms', 15000);
        $effectiveMs = max(1, min((int) $config->timeout_ms, $maxMs));

        return max(1, (int) ceil($effectiveMs / 1000));
    }

    /**
     * Traduz o status HTTP numa mensagem pt-BR categorizada. O status numérico
     * não é segredo; o corpo da resposta, sim — por isso nunca entra aqui.
     */
    private function categorize(int $status): string
    {
        return match (true) {
            $status === 401 || $status === 403 => 'Credencial inválida (autenticação recusada pelo provedor).',
            $status === 404 => 'Endpoint não encontrado no provedor (verifique a URL base).',
            $status === 429 => 'Limite de requisições do provedor atingido. Tente novamente em instantes.',
            $status >= 500 => "O provedor respondeu com erro interno (status {$status}).",
            default => "O provedor recusou a requisição (status {$status}).",
        };
    }
}
