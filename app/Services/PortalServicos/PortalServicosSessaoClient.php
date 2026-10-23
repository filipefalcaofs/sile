<?php

namespace App\Services\PortalServicos;

use App\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Confere no BFF do Portal de Serviços da SEDUR a sessão de um token
 * (GET /v1/acesso/buscar-sessao). 401, 403 e 404 dizem que a sessão não vale;
 * rede e 5xx dizem só que o BFF não respondeu, com uma nova tentativa.
 */
class PortalServicosSessaoClient
{
    private const TENTATIVAS = 2;

    /**
     * @throws SessaoPortalServicosInvalidaException
     * @throws PortalServicosIndisponivelException
     */
    public function buscarSessao(string $token): PortalServicosSessao
    {
        $response = $this->enviar($token);

        if (in_array($response->status(), [401, 403, 404], true)) {
            throw new SessaoPortalServicosInvalidaException('Portal de Serviços recusou a sessão.');
        }

        if (! $response->successful()) {
            throw new PortalServicosIndisponivelException('BFF do Portal de Serviços respondeu '.$response->status().'.');
        }

        $corpo = $response->json();

        return PortalServicosSessao::deResposta(is_array($corpo) ? $corpo : []);
    }

    private function enviar(string $token): Response
    {
        $url = rtrim((string) Settings::get('integrations.portal_servicos.bff_base_url'), '/').'/v1/acesso/buscar-sessao';

        for ($tentativa = 1; ; $tentativa++) {
            try {
                $response = Http::timeout((int) config('viabiliza.integrations.portal_servicos.timeout', 8))
                    ->connectTimeout(4)
                    ->acceptJson()
                    ->withToken($token)
                    ->get($url);
            } catch (ConnectionException $exception) {
                if ($tentativa >= self::TENTATIVAS) {
                    throw new PortalServicosIndisponivelException('BFF do Portal de Serviços inacessível.', previous: $exception);
                }

                continue;
            }

            if ($response->serverError() && $tentativa < self::TENTATIVAS) {
                continue;
            }

            return $response;
        }
    }
}
