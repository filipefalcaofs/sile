<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        // Sem guard fixo na rule: o default da request (web no portal,
        // gestao no console via auth:web,gestao) resolve o usuário correto.
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        app(AuditService::class)->log(
            'seguranca',
            'senha-alterada',
            'Senha alterada pelo próprio usuário',
        );
    }
}
