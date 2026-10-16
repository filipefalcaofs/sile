<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interruptor da consulta prévia de viabilidade do portal. O gestor liga e
 * desliga em Parâmetros (features.consulta_viabilidade), sem deploy. Desligado,
 * só a página e os endpoints da consulta respondem com aviso; os demais serviços
 * do portal e o histórico das consultas já feitas não passam por este gate.
 */
class EnsureConsultaViabilidadeEnabled
{
    public const MENSAGEM = 'A consulta prévia de viabilidade está temporariamente indisponível. Os demais serviços do portal continuam disponíveis.';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Settings::enabled('consulta_viabilidade')) {
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
