<?php

namespace App\Http\Requests\Portal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrimaryCnaeRequest extends FormRequest
{
    /**
     * Gerir CNAEs exige vínculo ATIVO do usuário efetivo (policy
     * manageCnaes, HU-025 CA-04). O 403 é auditado globalmente.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageCnaes', $this->route('company'));
    }

    /**
     * Seleção manual aceita SOMENTE CNAEs ativos da tabela oficial
     * (HU-025 CA-03) — regra distinta do import REDESIM, que aceita
     * inativos com aviso ([03-03]).
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'cnae_id' => ['required', 'integer', Rule::exists('cnaes', 'id')->where('active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnae_id.exists' => 'O CNAE informado não está ativo na tabela oficial.',
        ];
    }
}
