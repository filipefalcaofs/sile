<?php

namespace App\Http\Requests\Portal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta de viabilidade por INSCRIÇÃO imobiliária (HU-055). Rota pública
 * (cidadão anônimo): authorize true. A resolução do ponto depende da base de
 * lotes (pendente SEDUR — contrato PropertyRegistryLookup); enquanto
 * indisponível, o serviço degrada para a via CNAE com aviso. O `cnae` é exigido
 * (sustenta a análise por atividade); a `area` (m²) alimenta o Quadro 7.
 */
class ConsultaViabilidadeInscricaoRequest extends FormRequest
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
            'inscricao' => ['required', 'string', 'max:60'],
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
            'inscricao.required' => 'Informe a inscrição imobiliária para a consulta.',
            'cnae.required' => 'Informe o CNAE da atividade pretendida.',
            'area.numeric' => 'A área deve ser um número em metros quadrados.',
        ];
    }
}
