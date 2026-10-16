<?php

namespace App\Actions\Fortify;

use App\Models\Cidadao;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateCidadaoPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(Cidadao $user, array $input): void
    {
        // Sem guard fixo na rule: o guard padrão (web) é o do portal e autentica o cidadão.
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
