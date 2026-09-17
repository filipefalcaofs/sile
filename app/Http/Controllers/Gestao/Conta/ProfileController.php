<?php

namespace App\Http\Controllers\Gestao\Conta;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Perfil do servidor isolado no console (guard gestao) — espelha o do portal
 * (HU-007), mas resolve a conta pelo guard gestao e renderiza a tela própria.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user('gestao');

        return Inertia::render('gestao/conta/perfil', [
            'user' => $user->only('id', 'name', 'email', 'cpf', 'phone'),
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user('gestao');

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($user->wasChanged('email')) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', 'Perfil atualizado com sucesso.');
    }
}
