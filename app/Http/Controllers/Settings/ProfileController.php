<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Exibe os dados do próprio perfil (HU-007 CA-01).
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'user' => $request->user()->only('id', 'name', 'email', 'cpf', 'phone'),
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Atualiza name/email/phone; trocar o e-mail re-exige verificação
     * (HU-007 RN) e a mudança é auditada via HasAuditoria (CA-02).
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

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
