<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequirementRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-requisitos-documentais da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza o código (sem espaços nas pontas) e o booleano de
     * obrigatoriedade (checkbox ausente = false) antes da validação.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => trim((string) $this->input('code')),
            'required' => $this->boolean('required'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:document_requirements,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'required' => ['required', 'boolean'],
            'validation_instructions' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'código',
            'name' => 'nome',
            'description' => 'descrição',
            'required' => 'obrigatoriedade',
            'validation_instructions' => 'instruções de validação',
        ];
    }
}
