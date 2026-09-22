<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guest middleware da gestão sobre o guard próprio (gestao): quem já está
 * autenticado no console vai direto ao painel. A sessão do portal (guard
 * web) é invisível aqui — ambientes têm autenticações independentes.
 */
class RedirectGestaoIfAuthenticated
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('gestao')->check()) {
            return redirect()->route('gestao.dashboard');
        }

        return $next($request);
    }
}
