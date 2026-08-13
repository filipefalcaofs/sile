<?php

namespace App\Http\Requests\Gestao;

use App\Enums\DecisionOutcome;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação do payload do autosave da ficha (HU-135 RN-008). O autosave é
 * PARCIAL — só os campos presentes são validados/persistidos (`sometimes`). A
 * autorização é o middleware permission:analisar-processos da rota; a
 * imutabilidade da revisão finalizada (RN-003) é regra do AnalysisRecordService.
 *
 * O status escolhido por CNAE usa o MESMO vocabulário da sugestão do motor
 * (DecisionOutcome deferida/indeferida + 'analise' para encaminhar), permitindo
 * a comparação sugerido×escolhido que materializa as divergências (HU-140).
 */
class AnalysisRecordRequest extends FormRequest
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
        $statusEscolhido = [
            DecisionOutcome::Deferida->value,
            DecisionOutcome::Indeferida->value,
            'analise',
        ];

        return [
            'per_cnae' => ['sometimes', 'array'],
            'per_cnae.*.cnae' => ['required', 'string'],
            'per_cnae.*.status_escolhido' => ['required', Rule::in($statusEscolhido)],
            'per_cnae.*.justificativa' => ['nullable', 'string'],
            'per_cnae.*.condicionantes' => ['sometimes', 'array'],
            'conditions' => ['sometimes', 'array'],
            'parking' => ['sometimes', 'array'],
            'parecer' => ['sometimes', 'nullable', 'string'],
            'is_virtual_office_hq' => ['sometimes', 'nullable', 'boolean'],
            'analysis_reasons' => ['sometimes', 'array'],
            'analysis_reasons.*' => ['string'],
            'address_confirmed' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'per_cnae' => 'CNAEs da ficha',
            'per_cnae.*.cnae' => 'CNAE',
            'per_cnae.*.status_escolhido' => 'status escolhido',
            'per_cnae.*.justificativa' => 'justificativa',
            'parecer' => 'parecer',
        ];
    }
}
