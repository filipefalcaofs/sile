<?php

namespace App\Http\Requests\Gestao;

use App\Rules\TelefoneComDdd;
use App\Rules\ValidCpf;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-usuarios da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * CPF vai só com dígitos e e-mail em minúsculas, para a unicidade valer
     * independente da máscara e da caixa digitadas.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'cpf' => preg_replace('/\D/', '', (string) $this->input('cpf')),
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'cpf' => ['required', 'string', new ValidCpf, Rule::unique('users', 'cpf')],
            'phone' => ['nullable', 'string', 'max:20', new TelefoneComDdd],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'email' => 'e-mail',
            'cpf' => 'CPF',
            'phone' => 'telefone',
            'role' => 'papel',
        ];
    }
}
