<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Esqueci a senha" do console (guard gestao). Anti-oráculo: a resposta é
 * sempre genérica, e o link só é enviado para contas ativas com acesso ao
 * console — paridade de segurança com o login interno (HU-002/HU-012).
 */
class ForgotPasswordController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('auth/gestao-forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        $user = User::query()
            ->where('email', Str::lower((string) $request->input('email')))
            ->first();

        // Só contas ativas com acesso ao console recebem o link; a resposta
        // é sempre a mesma para não revelar a existência/estado da conta.
        if ($user && ! $user->isInactive() && $user->can('acessar-gestao')) {
            Password::broker('users')->sendResetLink(
                ['email' => $user->email],
                fn (User $u, string $token) => $u->sendGestaoPasswordResetNotification($token),
            );
        }

        return back()->with('status', __('Se a conta tiver acesso ao console, enviamos as instruções de recuperação.'));
    }
}
