<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarInscricaoContingenciaRequest extends FormRequest
{
    /**
     * A rota já exige a permissão registrar-contingencia.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'inscricao' => preg_replace('/\D/', '', (string) $this->input('inscricao')) ?? '',
        ]);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'inscricao' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'inscricao.required' => 'Informe a inscrição imobiliária.',
        ];
    }
}
