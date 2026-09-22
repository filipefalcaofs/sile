<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\GovBr\GovBr;
use App\Services\GovBr\GovBrAuthException;
use App\Services\GovBr\GovBrAuthService;
use App\Support\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

/**
 * HU-151 — autenticação via Login Único GOV.BR no portal do cidadão.
 *
 * Com o toggle desligado ou credenciais ausentes as rotas degradam com aviso
 * (RN-006 — nunca falha silenciosa). Sucesso e bloqueios ficam auditados
 * (RN-002): access_logs via listener de Login + activity 'login-govbr'.
 */
class GovBrLoginController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function redirect(): RedirectResponse
    {
        if (! GovBr::loginAvailable()) {
            return $this->unavailable();
        }

        return Socialite::driver('govbr')->redirect();
    }

    public function callback(Request $request, GovBrAuthService $service): RedirectResponse
    {
        if (! GovBr::loginAvailable()) {
            return $this->unavailable();
        }

        if ($request->filled('error')) {
            return $this->failed(__('A autorização no GOV.BR não foi concluída. Você pode tentar novamente ou entrar com e-mail e senha.'));
        }

        try {
            $user = $service->authenticate(Socialite::driver('govbr')->user());
        } catch (InvalidStateException) {
            return $this->failed(__('A sessão de autenticação expirou. Tente entrar com o GOV.BR novamente.'));
        } catch (GovBrAuthException $exception) {
            Log::warning('Login GOV.BR rejeitado', ['motivo' => $exception->getMessage()]);

            return $this->failed($exception->userMessage);
        }

        Auth::login($user);

        $request->session()->regenerate();

        $this->audit->log(
            'acessos',
            'login-govbr',
            'Login realizado com a conta GOV.BR',
            ['nivel_confiabilidade' => $user->govBrAccount?->reliability_level],
            $user,
        );

        // Mesma resposta do login local: destino por perfil e descarte de
        // URL pretendida incompatível (um servidor com conta GOV.BR vai
        // para a gestão, não para o painel do cidadão).
        return app(LoginResponse::class)->toResponse($request);
    }

    private function unavailable(): RedirectResponse
    {
        return $this->failed(__('O login com GOV.BR está indisponível no momento. Entre com e-mail e senha.'));
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
