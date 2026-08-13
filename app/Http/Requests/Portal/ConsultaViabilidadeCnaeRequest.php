<?php

namespace App\Http\Requests\Portal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta de viabilidade por CNAE (HU-056). Rota pública (cidadão anônimo):
 * authorize true. Roda o risco real e o Quadro 7 por área, SEM território — o
 * `cnae` é exigido (formato livre normalizado para dígitos pelo serviço) e a
 * `area` (m²) alimenta o Quadro 7 (HU-057).
 */
class ConsultaViabilidadeCnaeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
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
            'cnae.required' => 'Informe o CNAE da atividade pretendida.',
            'area.numeric' => 'A área deve ser um número em metros quadrados.',
        ];
    }
}
