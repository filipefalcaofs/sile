<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interruptor de um serviço do portal (renovação, consulta de processo ou
 * convite). O gestor liga e desliga em Parâmetros, aba Portal do cidadão
 * (features.<servico>), sem deploy. O interruptor geral continua mandando:
 * este só desliga o serviço em separado.
 */
class EnsurePortalServicoEnabled
{
    public const MENSAGEM = 'Este serviço do portal está temporariamente indisponível. Os demais serviços continuam disponíveis.';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $servico): Response
    {
        if (Settings::enabled($servico)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => self::MENSAGEM], 503);
        }

        return redirect()->route('portal.dashboard')->with('warning', self::MENSAGEM);
    }
}
