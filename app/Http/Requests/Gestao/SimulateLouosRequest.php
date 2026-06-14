<?php

namespace App\Http\Requests\Gestao;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Simulação e publicação de um rascunho de Quadro da LOUOS pelo sandbox (HU-143).
 * A autorização é o middleware permission:manter-louos da rota (simular/publicar
 * é manutenção). A versão rascunho deve EXISTIR como `rascunho` do domínio do
 * Quadro escolhido — só rascunhos são simuláveis/publicáveis; a regra dos quatro
 * olhos (publicador ≠ autor) é tratada no controller para comunicar o bloqueio
 * de forma controlada (flash.error). A amostra é opcional (lê o parâmetro quando
 * ausente).
 */
class SimulateLouosRequest extends FormRequest
{
    /**
     * Param `quadro` → domínio de regra versionada (rule_versions.domain).
     *
     * @var array<string, RuleDomain>
     */
    public const QUADRO_DOMAINS = [
        'quadro7' => RuleDomain::LouosQuadro7,
        'quadro10' => RuleDomain::LouosQuadro10,
        'quadro11' => RuleDomain::LouosQuadro11,
        'quadro11a' => RuleDomain::LouosQuadro11a,
    ];

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
        $dominio = (self::QUADRO_DOMAINS[$quadro] ?? null)?->value ?? '';

        return [
            'quadro' => ['required', 'string', 'in:quadro7,quadro10,quadro11,quadro11a'],
            'versao_rascunho' => [
                'required',
                'string',
                Rule::exists('rule_versions', 'version')
                    ->where('domain', $dominio)
                    ->where('status', RuleVersionStatus::Rascunho->value),
            ],
            'amostra' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Domínio de regra versionada correspondente ao Quadro escolhido (validado).
     */
    public function dominio(): RuleDomain
    {
        return self::QUADRO_DOMAINS[(string) $this->validated('quadro')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'quadro' => 'Quadro',
            'versao_rascunho' => 'versão rascunho',
            'amostra' => 'amostra',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'versao_rascunho.exists' => 'Não há um rascunho deste Quadro da LOUOS com esse identificador.',
        ];
    }
}
