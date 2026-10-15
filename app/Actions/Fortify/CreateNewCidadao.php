<?php

namespace App\Actions\Fortify;

use App\Models\Cidadao;
use App\Rules\TelefoneComDdd;
use App\Rules\ValidCpf;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewCidadao implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Cria a conta do cidadão ou assume o registro criado pelo servidor no
     * atendimento presencial (mesmo CPF, ainda sem senha).
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): Cidadao
    {
        $input['cpf'] = preg_replace('/\D/', '', (string) ($input['cpf'] ?? ''));

        $balcao = Cidadao::query()->where('cpf', $input['cpf'])->whereNull('password')->first();

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('cidadaos', 'email')->ignore($balcao?->id),
            ],
            'cpf' => [
                'required', 'string', new ValidCpf,
                Rule::unique('cidadaos', 'cpf')->ignore($balcao?->id),
            ],
            'phone' => ['required', 'string', 'max:20', new TelefoneComDdd],
            'password' => $this->passwordRules(),
        ], [], [
            'name' => 'nome',
            'cpf' => 'CPF',
            'phone' => 'telefone',
            'password' => 'senha',
        ])->validate();

        $dados = [
            'name' => $input['name'],
            'email' => $input['email'],
            'cpf' => $input['cpf'],
            'phone' => $input['phone'] ?? null,
            'password' => $input['password'],
        ];

        if ($balcao !== null) {
            $balcao->forceFill($dados)->save();

            return $balcao;
        }

        return Cidadao::create($dados);
    }
}
