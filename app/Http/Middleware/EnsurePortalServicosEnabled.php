<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interruptor dos serviços do portal do cidadão (consulta, solicitação,
 * renovação, empresas e procurações). O gestor liga e desliga em Parâmetros
 * (features.portal_servicos), sem deploy. Desligado, a entrada responde com
 * aviso. Painel, notificações e conta não passam por este gate.
 */
class EnsurePortalServicosEnabled
{
    public const MENSAGEM = 'Os serviços do portal estão temporariamente indisponíveis. Painel, notificações e dados da conta continuam disponíveis.';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Settings::enabled('portal_servicos')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => self::MENSAGEM], 503);
        }

        $destino = $request->user('web')
            ? route('portal.dashboard')
            : route('home');

        return redirect()->to($destino)->with('warning', self::MENSAGEM);
    }
}
