<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edição de UMA regra territorial no rascunho do domínio (hub Motor de
 * regras, aba Regras territoriais). A autorização é o middleware
 * permission:manter-louos da rota; a publicação do rascunho é quatro olhos
 * (endpoint próprio). CNAEs em 7 dígitos (padrão do cadastro); endereços com
 * CODLOG/número de porta/bairro conforme o tipo da regra. A versão só é usada
 * ao ABRIR o rascunho — com rascunho aberto, a edição reusa o existente.
 */
class TerritorialRuleEditRequest extends FormRequest
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
        return [
            'codigo' => ['required', 'string', 'max:50'],
            'version' => ['required', 'string', 'max:255'],
            'titulo' => ['required', 'string', 'max:255'],
            'ativo' => ['required', 'boolean'],
            'cod_log' => ['nullable', 'string', 'max:20'],
            'logradouro_nome' => ['nullable', 'string', 'max:255'],
            'camada' => ['nullable', 'string', 'max:255'],
            'camada_filtro' => ['nullable', 'string', 'max:500'],
            'zona_alvo' => ['nullable', 'string', 'max:60'],
            'motivo_analise' => ['nullable', 'string', 'max:2000'],
            'decision_text_id' => ['nullable', 'integer', 'exists:decision_texts,id'],
            'base_legal' => ['nullable', 'string', 'max:500'],
            'cnaes' => ['present', 'array'],
            'cnaes.*' => ['string', 'regex:/^\d{7}$/', 'distinct'],
            'addresses' => ['present', 'array'],
            'addresses.*.rotulo' => ['nullable', 'string', 'max:255'],
            'addresses.*.cod_log' => ['nullable', 'string', 'max:20'],
            'addresses.*.logradouro_nome' => ['nullable', 'string', 'max:255'],
            'addresses.*.numero_porta' => ['nullable', 'string', 'max:20'],
            'addresses.*.bairro' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'codigo' => 'código da regra',
            'version' => 'versão do rascunho',
            'titulo' => 'título',
            'cod_log' => 'código do logradouro (CODLOG)',
            'logradouro_nome' => 'nome do logradouro',
            'camada' => 'camada geográfica',
            'camada_filtro' => 'filtro da camada',
            'zona_alvo' => 'zona alvo',
            'motivo_analise' => 'motivo de análise',
            'base_legal' => 'base legal',
            'cnaes' => 'CNAEs',
            'cnaes.*' => 'CNAE',
            'addresses' => 'endereços',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnaes.*.regex' => 'Cada CNAE deve ter os 7 dígitos da subclasse (sem máscara).',
            'cnaes.*.distinct' => 'Há CNAE repetido na lista.',
        ];
    }
}
