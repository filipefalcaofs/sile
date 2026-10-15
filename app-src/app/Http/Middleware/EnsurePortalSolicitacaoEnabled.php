<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interruptor temporário das jornadas de criação do portal (solicitação inicial
 * e renovação de viabilidade). Controlado por SILE_PORTAL_SOLICITACAO; desligado,
 * bloqueia a entrada com aviso ao cidadão. Consulta, acompanhamento e
 * cancelamento de processos já existentes não passam por este gate.
 */
class EnsurePortalSolicitacaoEnabled
{
    public const MENSAGEM = 'O serviço de solicitação e renovação de viabilidade está temporariamente indisponível. Tente novamente mais tarde.';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('sile.features.portal_solicitacao', true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => self::MENSAGEM], 503);
        }

        return redirect()->route('portal.dashboard')->with('warning', self::MENSAGEM);
    }
}
