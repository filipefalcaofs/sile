<?php

namespace App\Http\Requests\Gestao;

use App\Support\Louos\LouosLinhaRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Armazenamento (store e update) de linha em rascunho de Quadro da LOUOS.
 * Usada tanto na criação quanto na edição; o controller passa o quadro como
 * parte do payload ou como parâmetro de rota.
 */
class StoreLouosLinhaRequest extends FormRequest
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
        $quadro = (string) ($this->input('quadro') ?? $this->route('quadro'));

        return [
            'quadro' => ['required', 'string', 'in:quadro10,quadro11a'],
            ...LouosLinhaRules::forQuadro($quadro),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'quadro' => 'Quadro',
            'cnae_code' => 'código CNAE',
            'area_min' => 'área mínima',
            'area_max' => 'área máxima',
            'grupo' => 'grupo',
            'subgrupo' => 'subgrupo',
            'observacao' => 'observação',
            'zona' => 'zona',
            'grupo_uso' => 'grupo de uso',
            'permissao' => 'permissão',
            'condicionante_ref' => 'referência de condicionante',
            'base_legal' => 'base legal',
            'classe_via' => 'classe de via',
            'condicoes' => 'condições',
        ];
    }
}
