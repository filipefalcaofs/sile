<?php

namespace App\Http\Middleware;

use App\Services\PortalServicos\PortalServicos;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Com a entrada pelo Portal de Serviços ligada (HU-142), o portal do cidadão
 * não tem login, cadastro, recuperação nem troca de senha próprios: as telas
 * levam ao Portal de Serviços e os envios respondem 404. O console não passa
 * por aqui — as rotas dele têm nomes gestao.*.
 */
class BloqueiaEntradaLocalDoPortal
{
    private const ROTAS = [
        'login',
        'login.store',
        'register',
        'register.store',
        'password.request',
        'password.email',
        'password.reset',
        'password.update',
        'user-password.update',
        'portal.conta.senha.show',
        'portal.conta.senha.update',
        'portal.govbr.redirect',
        'portal.govbr.callback',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs(...self::ROTAS) || ! PortalServicos::entradaDisponivel()) {
            return $next($request);
        }

        abort_unless($request->isMethod('GET') || $request->isMethod('HEAD'), 404);

        $destino = PortalServicos::urlLogin(PortalServicos::urlEntrada());

        // Navegação do Inertia (XHR) não segue redirect para outro domínio.
        return $request->header('X-Inertia') ? Inertia::location($destino) : redirect()->away($destino);
    }
}
