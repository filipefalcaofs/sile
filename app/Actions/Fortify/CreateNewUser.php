<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Rules\ValidCpf;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $input['cpf'] = preg_replace('/\D/', '', (string) ($input['cpf'] ?? ''));

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'cpf' => ['required', 'string', new ValidCpf, 'unique:users,cpf'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => $this->passwordRules(),
        ], [], [
            'name' => 'nome',
            'cpf' => 'CPF',
            'phone' => 'telefone',
            'password' => 'senha',
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'cpf' => $input['cpf'],
            'phone' => $input['phone'] ?? null,
            'password' => $input['password'],
        ]);

        $user->assignRole('cidadao');

        return $user;
    }
}
