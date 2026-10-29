<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class ProtocolarRenovacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'mobile' => ['required', 'string', 'max:20'],
            'declaration_ids' => ['present', 'array'],
            'declaration_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Informe o e-mail.',
            'phone.required' => 'Informe o telefone.',
            'mobile.required' => 'Informe o celular.',
            'email.email' => 'Informe um e-mail válido.',
        ];
    }
}
