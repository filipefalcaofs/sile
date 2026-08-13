<?php

namespace App\Http\Requests\Portal;

use App\Rules\ValidCnpj;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    /**
     * A rota já está protegida por auth + verified + lgpd.accepted; quem
     * cadastra vira responsável (HU-023).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza antes de validar: CNPJ sem máscara/uppercase (cobre o
     * alfanumérico de julho/2026); telefone e CEP só dígitos. A normalização
     * do CNPJ é o que garante o unique correto ([HU-021] anti-pattern).
     */
    protected function prepareForValidation(): void
    {
        $merge = [
            'cnpj' => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $this->input('cnpj'))),
        ];

        if ($this->filled('phone')) {
            $merge['phone'] = preg_replace('/\D/', '', (string) $this->input('phone'));
        }

        if ($this->filled('zip_code')) {
            $merge['zip_code'] = preg_replace('/\D/', '', (string) $this->input('zip_code'));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'cnpj' => ['required', 'string', new ValidCnpj, Rule::unique('companies', 'cnpj')],
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'legal_nature_code' => ['nullable', 'string', 'max:4'],
            'legal_nature' => ['nullable', 'string', 'max:255'],
            'size_code' => ['nullable', 'string', 'max:2'],
            'size' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],
            'zip_code' => ['nullable', 'string', 'size:8'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:11'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnpj.unique' => 'Já existe empresa cadastrada com este CNPJ.',
        ];
    }
}
