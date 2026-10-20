<?php

namespace App\Http\Requests\Portal;

use App\Rules\RespostaPlanilhaValida;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta de viabilidade por INSCRIÇÃO imobiliária (HU-053). Rota pública
 * (cidadão anônimo): authorize true. A resolução do ponto depende da base de
 * lotes (pendente SEDUR — contrato PropertyRegistryLookup); enquanto
 * indisponível, o serviço degrada para a via CNAE com aviso. O `cnae` é exigido
 * (sustenta a análise por atividade); a `area` (m²) alimenta o enquadramento da planilha.
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
            'area' => ['required', 'numeric', 'gt:0', 'max:9999999'],
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
            'inscricao.required' => 'Informe a inscrição imobiliária para a consulta.',
            'cnae.required' => 'Informe o CNAE da atividade pretendida.',
            'area.required' => 'Informe a área da atividade em metros quadrados.',
            'area.gt' => 'A área deve ser maior que zero.',
            'area.numeric' => 'A área deve ser um número em metros quadrados.',
            'categoria_empresa.in' => 'Informe uma categoria de empresa válida.',
            'vagas.*.numeric' => 'As vagas devem ser informadas em número.',
        ];
    }
}
