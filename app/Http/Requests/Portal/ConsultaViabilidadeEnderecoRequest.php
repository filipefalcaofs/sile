<?php

namespace App\Http\Requests\Portal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta de viabilidade por ENDEREÇO (HU-054). Rota pública (cidadão anônimo):
 * authorize true. O `cnae` é sempre exigido (a viabilidade é de uma atividade);
 * o formato livre é normalizado para dígitos pelo serviço. `area` (m²) alimenta
 * o Quadro 7 (HU-057).
 */
class ConsultaViabilidadeEnderecoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Remove espaços nas pontas do endereço antes de validar.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('endereco')) {
            $this->merge(['endereco' => trim((string) $this->input('endereco'))]);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'endereco' => ['required', 'string', 'min:3', 'max:255'],
            'cnae' => ['required', 'string', 'max:14'],
            'area' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'endereco.required' => 'Informe o endereço para a consulta de viabilidade.',
            'endereco.min' => 'O endereço deve ter ao menos 3 caracteres.',
            'cnae.required' => 'Informe o CNAE da atividade pretendida.',
            'area.numeric' => 'A área deve ser um número em metros quadrados.',
        ];
    }
}
