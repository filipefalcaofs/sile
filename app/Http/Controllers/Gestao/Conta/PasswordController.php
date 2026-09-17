<?php

namespace App\Http\Controllers\Gestao\Conta;

use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Controllers\Controller;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Alteração de senha do servidor no console (guard gestao). Reusa a action
 * de validação/atualização (com auditoria RN-002), resolvendo a conta pelo
 * guard gestao — sem depender da rota do Fortify (guard web).
 */
class PasswordController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('gestao/conta/senha', [
            'passwordRules' => PasswordPolicy::description(),
        ]);
    }

    public function update(Request $request, UpdateUserPassword $updater): RedirectResponse
    {
        $updater->update(
            $request->user('gestao'),
            $request->only('current_password', 'password', 'password_confirmation'),
        );

        return back();
    }
}
