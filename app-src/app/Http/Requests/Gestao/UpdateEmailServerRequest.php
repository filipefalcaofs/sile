<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailServerRequest extends FormRequest
{
    /**
     * Autorização é o middleware permission:manter-config-email da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->has('active') ? $this->boolean('active') : true,
            'is_default' => $this->boolean('is_default'),
        ]);
    }

    /**
     * A senha é opcional na edição: em branco = manter a atual (RN-009, o
     * controller não sobrescreve). Demais campos são atualização completa.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', 'in:smtp'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', 'string', 'in:tls,ssl,none'],
            'timeout' => ['required', 'integer', 'min:1', 'max:300'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
        ];
    }
}
