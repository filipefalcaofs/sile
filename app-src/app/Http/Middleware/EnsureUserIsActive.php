<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Efeito imediato da inativação (HU-012): sessão aberta de usuário
 * inativado é derrubada na request seguinte, com mensagem clara. Resolve
 * pelo guard do ambiente da request (console x portal), já que as sessões
 * são isoladas por cookie/path. Guest e usuário ativo: no-op.
 */
class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = $request->is('gestao', 'gestao/*') ? 'gestao' : 'web';

        $user = Auth::guard($guard)->user();

        if ($user !== null && $user->isInactive()) {
            Auth::guard($guard)->logout();
            $request->session()->regenerateToken();

            $loginRoute = $guard === 'gestao' ? 'gestao.login' : 'login';

            return redirect()->route($loginRoute)->withErrors([
                'email' => __('Sua conta está inativa. Procure o administrador do sistema.'),
            ]);
        }

        return $next($request);
    }
}
