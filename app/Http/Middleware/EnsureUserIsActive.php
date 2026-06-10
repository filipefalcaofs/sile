<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Efeito imediato da inativação (HU-012): sessão aberta de usuário
 * inativado é derrubada na request seguinte, com mensagem clara.
 * Guest e usuário ativo: no-op.
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
        $user = $request->user();

        if ($user !== null && $user->isInactive()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('Sua conta está inativa. Procure o administrador do sistema.'),
            ]);
        }

        return $next($request);
    }
}
