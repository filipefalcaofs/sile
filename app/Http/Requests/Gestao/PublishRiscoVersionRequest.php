<?php

namespace App\Http\Requests\Gestao;

use App\Enums\RiscoMunicipal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Publicação versionada da tabela de risco municipal (HU-020/HU-053). A
 * autorização é o middleware permission:manter-risco da rota; a regra dos
 * quatro olhos (autor distinto do publicador) é verificada no controller para
 * comunicar o bloqueio de forma controlada (flash.error), não como erro de
 * validação. A versão é única no domínio (não reescreve a vigente).
 */
class PublishRiscoVersionRequest extends FormRequest
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
            'version' => [
                'required',
                'string',
                'max:255',
                Rule::unique('rule_versions', 'version')->where('domain', 'risco_municipal'),
            ],
            'author_id' => ['required', 'integer', 'exists:users,id'],
            'alteracoes' => ['sometimes', 'array'],
            'alteracoes.*.cnae_code' => ['required', 'string', 'max:14'],
            'alteracoes.*.risco_municipal' => ['required', Rule::enum(RiscoMunicipal::class)],
            'alteracoes.*.condicionantes' => ['sometimes', 'array'],
            'alteracoes.*.condicionantes.*' => ['string'],
            'alteracoes.*.observacao' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'version' => 'versão',
            'author_id' => 'autor',
            'alteracoes' => 'alterações',
            'alteracoes.*.cnae_code' => 'código do CNAE',
            'alteracoes.*.risco_municipal' => 'nível de risco municipal',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.unique' => 'Já existe uma versão da tabela de risco municipal com este identificador.',
        ];
    }
}
