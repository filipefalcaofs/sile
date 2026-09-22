<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Isola a sessão por ambiente: o console (/gestao) e o portal do cidadão usam
 * cookies de sessão distintos, com paths disjuntos (/gestao × /portal). Ajusta
 * também o path/domínio padrão do CookieJar, para que o cookie CSRF
 * (XSRF-TOKEN) e o "lembrar-me" herdem o escopo do ambiente.
 *
 * Registrado como middleware GLOBAL (não do grupo web): precisa rodar ANTES do
 * roteamento. O Router instancia o controller na coleta de middleware
 * (gatherRouteMiddleware → getController) e o controller do Fortify injeta o
 * guard web no construtor, resolvendo o session.store antes da pilha web. Como
 * global, esta config vale antes dessa resolução, fixando o nome do cookie
 * certo. A identidade por ambiente é resolvida por guard explícito nos
 * consumidores (HandleInertiaRequests, EnsureLgpdTermAccepted,
 * EnsureUserIsActive) e pelos middlewares auth:web/auth:gestao.
 */
class ConfigureEnvironmentSession
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isConsole = $request->is('gestao', 'gestao/*');

        $cookie = $isConsole ? 'sile_gestao_session' : 'sile_portal_session';
        $path = $isConsole ? '/gestao' : '/portal';

        config([
            'session.cookie' => $cookie,
            'session.path' => $path,
        ]);

        cookie()->setDefaultPathAndDomain(
            $path,
            config('session.domain'),
            (bool) config('session.secure'),
            config('session.same_site'),
        );

        return $next($request);
    }
}
