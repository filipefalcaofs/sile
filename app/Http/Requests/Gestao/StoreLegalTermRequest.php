<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLegalTermRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-parametros da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => trim((string) $this->input('type')),
        ]);
    }

    /**
     * A versão NUNCA vem do usuário: é computada no controller (max+1 por
     * tipo). Um `version` enviado é descartado por não constar nas regras.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'tipo',
            'title' => 'título',
            'content' => 'conteúdo',
        ];
    }
}
