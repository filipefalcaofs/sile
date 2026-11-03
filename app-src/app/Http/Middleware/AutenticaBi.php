<?php

namespace App\Http\Middleware;

use App\Services\Integracao\TokenBiService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege a API de indicadores do BI: 503 com a integração desligada ou sem
 * credencial configurada, 401 sem token Bearer válido.
 */
class AutenticaBi
{
    public function __construct(private readonly TokenBiService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tokens->configurado()) {
            return response()->json(['mensagem' => 'Integração indisponível.'], 503);
        }

        if (! $this->tokens->valido((string) $request->bearerToken())) {
            return response()->json(['mensagem' => 'Token ausente, inválido ou expirado.'], 401);
        }

        return $next($request);
    }
}
