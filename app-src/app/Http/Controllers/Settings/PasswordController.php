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
     * Endpoint do portal (guard web). A rota do Fortify também atende, mas
     * a tela usa este endpoint sob /portal/conta para manter o ambiente.
     */
    public function update(Request $request, UpdateUserPassword $updater): RedirectResponse
    {
        $updater->update(
            $request->user('web'),
            $request->only('current_password', 'password', 'password_confirmation'),
        );

        return back();
    }
}
