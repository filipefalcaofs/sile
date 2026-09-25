<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\LoginRequest;
use App\Models\AccessLog;
use App\Models\User;
use App\Services\Auth\DirectoryAuthenticator;
use App\Services\Auth\UserSectorAssignmentSync;
use App\Services\Sedur\SedurAuthInvalidCredentialsException;
use App\Services\Sedur\SedurAuthSettings;
use App\Services\Sedur\SedurAuthUnavailableException;
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
 * do portal do cidadão. Login híbrido — contas com auth_type=ad validam a
 * credencial na API institucional SEDUR (DirectoryAuthenticator); contas
 * locais validam o hash em users.password. Em ambos os casos a sessão é
 * Laravel e valem as mesmas proteções: throttle parametrizado (HU-014) por
 * conta e por IP com Lockout auditável, anti-oráculo, conta inativada
 * (HU-012) e tentativa sem permissão bloqueada e registrada (HU-002 CA-04).
 * Sem fallback: conta AD nunca valida contra a senha local.
 */
class LoginController extends Controller
{
    public function __construct(
        private DirectoryAuthenticator $directory,
        private SedurAuthSettings $sedurAuth,
    ) {}

    public function store(LoginRequest $request): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);

        $identifier = Str::lower($request->validated('identifier'));

        $user = User::query()
            ->where(function ($query) use ($identifier) {
                $query->where('email', $identifier)
                    ->orWhere('ad_username', $identifier);
            })
            ->first();

        // Anti-oráculo: o estado da conta (inativa, sem permissão) só é
        // revelado com credencial correta, e a falha de credencial tem a
        // mesma mensagem para conta local, AD ou inexistente.
        if (! $user || ! $this->credencialValida($request, $user)) {
            RateLimiter::hit($this->throttleKey($request), 60);
            RateLimiter::hit($this->ipThrottleKey($request), 60);

            event(new Failed('gestao', $user, [
                'email' => $user?->email ?? $request->validated('identifier'),
                'origin' => $user === null ? null : ($user->isAdAccount() ? 'ad' : 'local'),
            ]));

            throw ValidationException::withMessages(['identifier' => __('auth.failed')]);
        }

        if ($user->isInactive()) {
            AccessLog::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'event' => 'inativada',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'channel' => 'gestao',
                'origin' => $user->isAdAccount() ? 'ad' : 'local',
            ]);

            throw ValidationException::withMessages([
                'identifier' => __('Sua conta está inativa. Procure o administrador do sistema.'),
            ]);
        }

        if (! $user->can('acessar-gestao')) {
            app(AuditService::class)->logBlocked('seguranca', 'Tentativa de login no console sem permissão', [
                'email' => $user->email,
                'user_id' => $user->id,
            ]);

            throw ValidationException::withMessages([
                'identifier' => __('Esta conta não tem acesso ao console de gestão.'),
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'identifier' => __('Confirme seu e-mail institucional antes de acessar o console.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));
        RateLimiter::clear($this->ipThrottleKey($request));

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

    /**
     * Verifica a credencial pelo mecanismo da conta. A senha digitada é
     * transitória: repassada ao diretório (conta AD) ou ao hash local, e
     * descartada ao fim da request — nunca persistida nem logada.
     */
    private function credencialValida(LoginRequest $request, User $user): bool
    {
        if (! $user->isAdAccount()) {
            $this->markOrigin($request, 'local');

            return Hash::check($request->validated('password'), $user->password);
        }

        $this->markOrigin($request, 'ad');

        if (! $this->sedurAuth->enabled()) {
            app(AuditService::class)->logBlocked('seguranca', 'Login AD bloqueado: integração desabilitada (features.ad_login)', [
                'email' => $user->email,
                'user_id' => $user->id,
            ]);

            throw ValidationException::withMessages([
                'identifier' => __('A autenticação corporativa está desabilitada. Procure o administrador do sistema.'),
            ]);
        }

        try {
            $resultado = $this->directory->attempt(
                (string) $user->ad_username,
                $request->validated('password'),
                $user->cpf,
            );
        } catch (SedurAuthInvalidCredentialsException) {
            return false;
        } catch (SedurAuthUnavailableException) {
            AccessLog::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'event' => 'ad-indisponivel',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'channel' => 'gestao',
                'origin' => 'ad',
            ]);

            throw ValidationException::withMessages([
                'identifier' => __('Autenticação institucional indisponível no momento. Tente novamente em instantes.'),
            ]);
        }

        $sincronizacaoFalhou = $resultado->sincronizacaoFalhou;

        // Sincronização cadastral com minimização: somente o nome oficial.
        // name está no fillable — a auditoria HasAuditoria registra a mudança.
        if ($resultado->nome !== null && $resultado->nome !== $user->name) {
            $user->update(['name' => $resultado->nome]);
        }

        // Lotação via discovery (mesmo token transitório). Falha aqui não
        // derruba o login — identidade já validada no /auth.
        if ($resultado->setorCodigo !== null || $resultado->setorNome !== null) {
            try {
                app(UserSectorAssignmentSync::class)->syncFromDiscovery(
                    $user,
                    $resultado->setorCodigo,
                    $resultado->setorNome,
                );
            } catch (\Throwable) {
                $sincronizacaoFalhou = true;
            }
        }

        if ($sincronizacaoFalhou) {
            AccessLog::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'event' => 'ad-sincronizacao-falhou',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'channel' => 'gestao',
                'origin' => 'ad',
            ]);
        }

        return true;
    }

    protected function ensureIsNotRateLimited(LoginRequest $request): void
    {
        $this->ensureIpIsNotRateLimited($request);

        $max = (int) Settings::get('security.login.max_attempts');

        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), $max)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'identifier' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Limite por IP (além do por conta): contém password spraying — muitas
     * contas, uma senha — que a chave email|ip não pega. Sem ele, o SILE
     * vira proxy de força bruta contra o AD institucional.
     */
    private function ensureIpIsNotRateLimited(LoginRequest $request): void
    {
        $maxPorIp = (int) config('sile.security.login.max_attempts_per_ip', 30);

        if (! RateLimiter::tooManyAttempts($this->ipThrottleKey($request), $maxPorIp)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->ipThrottleKey($request));

        throw ValidationException::withMessages([
            'identifier' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * O listener de login lê request() no container. O FormRequest injetado
     * pode ser outra instância — a origem precisa estar nas duas.
     */
    private function markOrigin(LoginRequest $request, string $origin): void
    {
        $request->attributes->set('auth_origin', $origin);
        request()->attributes->set('auth_origin', $origin);
    }

    protected function throttleKey(LoginRequest $request): string
    {
        return Str::transliterate(Str::lower($request->validated('identifier')).'|'.$request->ip());
    }

    protected function ipThrottleKey(LoginRequest $request): string
    {
        return 'gestao-login-ip|'.$request->ip();
    }
}
