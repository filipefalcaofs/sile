<?php

namespace App\Http\Requests\Portal;

use App\Rules\RespostaPlanilhaValida;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta de viabilidade por CNAE (HU-054). Rota pública (cidadão anônimo):
 * authorize true. Roda o risco real e o enquadramento da planilha por área, SEM território — o
 * `cnae` é exigido (formato livre normalizado para dígitos pelo serviço) e a
 * `area` (m²) alimenta o ramo vigente.
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
            'respostas' => ['nullable', 'array'],
            'respostas.*' => [new RespostaPlanilhaValida],
            'tipo_imovel' => ['nullable', 'string', 'max:120'],
            'categoria_empresa' => ['nullable', 'in:orgao_publico,outra'],
            'vagas' => ['nullable', 'array'],
            'vagas.*' => ['numeric', 'min:0', 'max:99999'],
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
            'categoria_empresa.in' => 'Informe uma categoria de empresa válida.',
            'vagas.*.numeric' => 'As vagas devem ser informadas em número.',
        ];
    }
}
