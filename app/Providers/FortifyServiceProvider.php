<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\AccessLog;
use App\Models\User;
use App\Services\GovBr\GovBr;
use App\Support\PasswordPolicy;
use App\Support\Settings;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Ambientes com sessões independentes (decisão 2026-06-12): o login
        // do portal leva sempre ao portal — a gestão exige o login interno
        // (/gestao/login, guard gestao). URL pretendida da gestão é
        // descartada para ninguém aterrissar no login interno sem querer.
        $this->app->instance(LoginResponse::class, new class implements LoginResponse
        {
            public function toResponse($request)
            {
                $intendedPath = (string) parse_url(
                    (string) $request->session()->get('url.intended', ''),
                    PHP_URL_PATH,
                );

                if ($intendedPath === '/gestao' || str_starts_with($intendedPath, '/gestao/')) {
                    $request->session()->forget('url.intended');
                }

                return redirect()->intended(route('portal.dashboard'));
            }
        });

        // fortify.limiters.login = null mantém o Lockout auditável no pipeline
        // (decisão 01-02), mas o LoginRateLimiter do Fortify fixa 5 tentativas;
        // esta subclasse faz o limite vir do parâmetro administrável (HU-014).
        $this->app->singleton(LoginRateLimiter::class, function ($app) {
            return new class($app->make(CacheRateLimiter::class)) extends LoginRateLimiter
            {
                public function tooManyAttempts(Request $request): bool
                {
                    return $this->limiter->tooManyAttempts(
                        $this->throttleKey($request),
                        (int) Settings::get('security.login.max_attempts'),
                    );
                }

                /**
                 * CPF normalizado para dígitos: alternar máscara não pode
                 * criar chaves de throttle diferentes para a mesma conta.
                 */
                protected function throttleKey(Request $request): string
                {
                    $username = preg_replace('/\D/', '', (string) $request->input(Fortify::username()));

                    return Str::transliterate($username.'|'.$request->ip());
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canLoginWithGovBr' => GovBr::loginAvailable(),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => PasswordPolicy::description(),
            'canLoginWithGovBr' => GovBr::loginAvailable(),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        // Rota GET registrada pelo Fortify independentemente de feature:
        // sem a view, ações futuras protegidas por password.confirm dariam
        // erro 500 em vez de pedir a senha.
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));

        // Login do cidadão por CPF (normalizado para dígitos — o form envia
        // com máscara). HU-012: conta inativada não autentica; retorno null
        // preserva o fluxo padrão (evento Failed -> access_log 'falha') sem
        // revelar o estado da conta — a mensagem de inatividade só aparece
        // com credenciais corretas.
        Fortify::authenticateUsing(function (Request $request) {
            $cpf = preg_replace('/\D/', '', (string) $request->input('cpf'));

            $user = User::query()->where('cpf', $cpf)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return null;
            }

            if ($user->isInactive()) {
                AccessLog::create([
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'event' => 'inativada',
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                    'channel' => $request->routeIs('gestao.*') ? 'gestao' : 'portal',
                ]);

                throw ValidationException::withMessages([
                    'cpf' => __('Sua conta está inativa. Procure o administrador do sistema.'),
                ]);
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            $username = preg_replace('/\D/', '', (string) $request->input(Fortify::username()));
            $throttleKey = Str::transliterate($username.'|'.$request->ip());

            return Limit::perMinute((int) Settings::get('security.login.max_attempts'))->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });

        // Throttle da consulta pública de CNPJ (HU-021) — limite administrável
        // sem deploy; estabelece o padrão de throttle parametrizado para HU-069/EP07.
        RateLimiter::for('cnpj-lookup', function (Request $request) {
            return Limit::perMinute(
                (int) Settings::get('seguranca.throttle.cnpj_lookup.por_minuto', 30),
            )->by($request->user()?->id ?: $request->ip());
        });
    }
}
