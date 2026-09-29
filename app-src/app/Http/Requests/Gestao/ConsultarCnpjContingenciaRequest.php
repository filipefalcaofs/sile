<?php

namespace App\Http\Requests\Gestao;

use App\Rules\ValidCnpj;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarCnpjContingenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnpj' => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $this->input('cnpj'))),
        ]);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'cnpj' => ['required', 'string', new ValidCnpj],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnpj.required' => 'Informe o CNPJ da empresa.',
        ];
    }
}
