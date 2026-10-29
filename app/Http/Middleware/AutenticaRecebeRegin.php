<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege o POST /api_integracao/recebe. Cada controle só vale quando está
 * configurado, para a publicação não derrubar o recebimento da JUCEB antes
 * de ela passar a enviar o segredo: segredo compartilhado (header
 * `X-Regin-Token` ou `Authorization: Bearer`), lista de IPs de origem e teto
 * do tamanho do corpo. Recusa antes de qualquer gravação.
 */
class AutenticaRecebeRegin
{
    public function handle(Request $request, Closure $next): Response
    {
        $config = (array) config('viabiliza.integrations.regin.recebe', []);

        $ips = array_filter((array) ($config['ips_permitidos'] ?? []));

        if ($ips !== [] && ! in_array($request->ip(), $ips, true)) {
            return $this->recusa(403);
        }

        $segredo = (string) ($config['segredo'] ?? '');

        if ($segredo !== '' && ! hash_equals($segredo, $this->tokenEnviado($request))) {
            return $this->recusa(401);
        }

        $limite = (int) ($config['limite_corpo'] ?? 0);

        if ($limite > 0 && strlen($request->getContent()) > $limite) {
            return $this->recusa(413);
        }

        return $next($request);
    }

    private function tokenEnviado(Request $request): string
    {
        $header = (string) $request->header('X-Regin-Token', '');

        return $header !== '' ? $header : (string) $request->bearerToken();
    }

    private function recusa(int $status): Response
    {
        return response('', $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
