<?php

namespace App\Http\Requests\Portal;

use App\Rules\ValidCnpj;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LookupCnpjRequest extends FormRequest
{
    /**
     * A rota já está protegida por auth + verified + lgpd.accepted.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza o CNPJ (sem máscara, uppercase) antes de validar — cobre o
     * formato alfanumérico que entra em produção em julho/2026.
     */
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
}
