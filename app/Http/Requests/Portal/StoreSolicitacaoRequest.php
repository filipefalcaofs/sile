<?php

namespace App\Http\Requests\Portal;

use App\Models\Company;
use App\Support\Representation\CurrentRepresentation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSolicitacaoRequest extends FormRequest
{
    /**
     * A rota já está protegida por auth:web + verified + lgpd.accepted +
     * ResolveRepresentation; o escopo por empresa do efetivo é garantido nas
     * regras abaixo (não se cria solicitação para empresa de terceiro).
     */
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
            // Só um tipo de serviço ATIVO (08-03) pode ser escolhido (RN-005).
            'service_type_id' => [
                'required', 'integer',
                Rule::exists('viability_service_types', 'id')->where('active', true),
            ],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ];
    }

    /**
     * A empresa deve ter vínculo ATIVO do usuário EFETIVO (representado quando
     * "em nome de" — Fase 1). Reusa a lógica de escopo da Fase 3.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $companyId = $this->input('company_id');

                if ($companyId === null || $validator->errors()->has('company_id')) {
                    return;
                }

                $effectiveUser = app(CurrentRepresentation::class)->grantor() ?? $this->user();

                $hasActiveLink = Company::query()
                    ->whereKey($companyId)
                    ->whereHas('links', fn ($query) => $query
                        ->where('user_id', $effectiveUser->id)
                        ->whereNull('ended_at'))
                    ->exists();

                if (! $hasActiveLink) {
                    $validator->errors()->add('company_id', 'Você não possui vínculo ativo com esta empresa.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'service_type_id.required' => 'Selecione o tipo de serviço.',
            'service_type_id.exists' => 'O tipo de serviço selecionado é inválido ou está inativo.',
            'company_id.required' => 'Selecione a empresa da solicitação.',
            'company_id.exists' => 'A empresa selecionada é inválida.',
        ];
    }
}
