<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\LoginRequest;
use App\Models\AccessLog;
use App\Models\User;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Autenticação do console em guard próprio (gestao): sessão independente
 * do portal do cidadão. Paridade de segurança com o login do portal —
 * throttle parametrizado (HU-014) com Lockout auditável, anti-oráculo,
 * conta inativada (HU-012) e tentativa sem permissão bloqueada e
 * registrada (HU-002 CA-04). E-mail verificado é pré-condição de login
 * aqui (as rotas internas não usam o middleware verified, cujo aviso de
 * verificação pertence ao fluxo do portal).
 */
class LoginController extends Controller
{
    public function store(LoginRequest $request): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);

        $user = User::query()
            ->where('email', Str::lower($request->validated('email')))
            ->first();

        // Anti-oráculo: o estado da conta (inativa, sem permissão) só é
        // revelado com credencial correta.
        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey($request), 60);

            event(new Failed('gestao', $user, ['email' => $request->validated('email')]));

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if ($user->isInactive()) {
            AccessLog::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'event' => 'inativada',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'channel' => 'gestao',
            ]);

            throw ValidationException::withMessages([
                'email' => __('Sua conta está inativa. Procure o administrador do sistema.'),
            ]);
        }

        if (! $user->can('acessar-gestao')) {
            app(AuditService::class)->logBlocked('seguranca', 'Tentativa de login no console sem permissão', [
                'email' => $user->email,
                'user_id' => $user->id,
            ]);

            throw ValidationException::withMessages([
                'email' => __('Esta conta não tem acesso ao console de gestão.'),
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => __('Confirme seu e-mail pelo portal antes de acessar o console.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        Auth::guard('gestao')->login($user, $request->boolean('remember'));

        // Novo ID de sessão contra fixation, preservando os dados existentes
        // (inclusive um eventual login do portal no guard web).
        $request->session()->regenerate();

        return redirect()->intended(route('gestao.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('gestao')->logout();

        // Logout apenas do guard da gestão: invalidar a sessão inteira
        // derrubaria também a sessão do portal (cookie único por domínio).
        $request->session()->regenerateToken();

        return redirect()->route('gestao.login');
    }

    protected function ensureIsNotRateLimited(LoginRequest $request): void
    {
        $max = (int) Settings::get('security.login.max_attempts');

        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), $max)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(LoginRequest $request): string
    {
        return Str::transliterate(Str::lower($request->validated('email')).'|'.$request->ip());
    }
}
