<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Abertura de rascunho de um Quadro da LOUOS. A versão é nullable aqui porque
 * o controller decide se ela é obrigatória com base na existência de rascunho
 * aberto — o service valida a unicidade de domínio no fluxo de criação.
 */
class OpenLouosDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $quadro = (string) $this->input('quadro');
        $domain = PublishLouosVersionRequest::QUADRO_DOMAINS[$quadro] ?? '';

        return [
            'quadro' => ['required', 'string', 'in:quadro10,quadro11a'],
            'version' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('rule_versions', 'version')->where('domain', $domain),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'quadro' => 'Quadro',
            'version' => 'versão',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.unique' => 'Já existe uma versão deste Quadro da LOUOS com este identificador.',
        ];
    }
}
