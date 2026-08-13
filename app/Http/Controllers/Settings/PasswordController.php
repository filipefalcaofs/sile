<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Controllers\Controller;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('settings/password', [
            'passwordRules' => PasswordPolicy::description(),
        ]);
    }

    /**
     * Endpoint próprio (multi-guard) — a rota do Fortify exige o guard web
     * e não atende quem está autenticado apenas no console.
     */
    public function update(Request $request, UpdateUserPassword $updater): RedirectResponse
    {
        $updater->update(
            $request->user(),
            $request->only('current_password', 'password', 'password_confirmation'),
        );

        return back();
    }
}
