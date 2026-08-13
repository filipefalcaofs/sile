<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Efeito imediato da inativação (HU-012): sessão aberta de usuário
 * inativado é derrubada na request seguinte, com mensagem clara — nos
 * dois guards de sessão (portal e gestão). Guest e usuário ativo: no-op.
 */
class EnsureUserIsActive
{
    private const GUARDS = ['web', 'gestao'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $contextGuard = $request->is('gestao', 'gestao/*') ? 'gestao' : 'web';
        $contextDropped = false;

        foreach (self::GUARDS as $guard) {
            $user = Auth::guard($guard)->user();

            if ($user !== null && $user->isInactive()) {
                // Derruba apenas o guard com a conta inativa: os guards podem
                // carregar contas diferentes na mesma sessão de navegador.
                Auth::guard($guard)->logout();

                if ($guard === $contextGuard) {
                    $contextDropped = true;
                }
            }
        }

        if ($contextDropped) {
            $request->session()->regenerateToken();

            $loginRoute = $contextGuard === 'gestao' ? 'gestao.login' : 'login';

            return redirect()->route($loginRoute)->withErrors([
                'email' => __('Sua conta está inativa. Procure o administrador do sistema.'),
            ]);
        }

        return $next($request);
    }
}
