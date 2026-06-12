<?php

namespace App\Http\Requests\Portal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    /**
     * A autorização efetiva é a policy update no controller (Gate::authorize),
     * que exige vínculo ATIVO do usuário efetivo (HU-024 CA-04).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza telefone e CEP para só dígitos, espelhando o StoreCompanyRequest.
     * O CNPJ NÃO é normalizado nem validado: é imutável (HU-024), então o valor
     * enviado simplesmente não entra em validated() (padrão CPF [01-08]).
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->filled('phone')) {
            $merge['phone'] = preg_replace('/\D/', '', (string) $this->input('phone'));
        }

        if ($this->filled('zip_code')) {
            $merge['zip_code'] = preg_replace('/\D/', '', (string) $this->input('zip_code'));
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * MESMAS regras do StoreCompanyRequest EXCETO cnpj: o campo não entra nas
     * regras, logo validated() nunca o contém e o update o ignora (imutável).
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
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
}
