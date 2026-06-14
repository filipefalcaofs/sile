<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequirementRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-requisitos-documentais da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * O código é imutável na edição (padrão CPF/CNAE das Fases 1/2: o valor
     * enviado é ignorado) e a situação é alternada pela ação de toggle —
     * por isso nenhum dos dois entra nas regras. Normaliza o booleano de
     * obrigatoriedade (checkbox ausente = false).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'required' => $this->boolean('required'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
            'name' => 'nome',
            'description' => 'descrição',
            'required' => 'obrigatoriedade',
            'validation_instructions' => 'instruções de validação',
        ];
    }
}
