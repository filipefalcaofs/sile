<?php

namespace App\Http\Requests\Portal;

use App\Support\Settings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Instrução das atividades da solicitação (HU-064/HU-065): a atividade
 * principal e os CNAEs complementares vêm da tabela oficial e SÓ aceitam
 * CNAEs ativos (mesma regra da seleção manual da Fase 3, [03-06]). O limite
 * de complementares é administrável (HU-014) e lido dinamicamente do catálogo
 * (banco→cache→config), espelhando a validação dinâmica do [02-07].
 */
class UpdateSolicitacaoAtividadesRequest extends FormRequest
{
    /**
     * A autorização fina (dono + rascunho) é feita no controller via
     * Gate::authorize('update', ...) com a ViabilityRequestPolicy (08-05). A
     * rota já exige auth:web + verified + lgpd.accepted + ResolveRepresentation.
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
        $max = (int) Settings::get(
            'solicitacao.cnaes_complementares.max',
            config('sile.solicitacao.cnaes_complementares.max', 99),
        );

        return [
            // Exatamente uma atividade principal, da tabela oficial e ATIVA.
            'principal_cnae_id' => ['required', 'integer', Rule::exists('cnaes', 'id')->where('active', true)],
            // Complementares opcionais, até o limite parametrizável, sem duplicados e ATIVOS.
            'complementares' => ['nullable', 'array', "max:{$max}"],
            'complementares.*' => ['integer', 'distinct', Rule::exists('cnaes', 'id')->where('active', true)],
        ];
    }

    /**
     * A atividade principal nunca aparece entre os complementares (intenções
     * distintas — espelha a separação principal × secundários do [03-06]).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $primaryId = (int) $this->input('principal_cnae_id');

                if ($primaryId === 0) {
                    return;
                }

                $complementares = array_map('intval', (array) $this->input('complementares', []));

                if (in_array($primaryId, $complementares, true)) {
                    $validator->errors()->add('complementares', 'A atividade principal não pode estar entre os CNAEs complementares.');
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
            'principal_cnae_id.required' => 'Selecione a atividade principal.',
            'principal_cnae_id.exists' => 'A atividade principal informada não está ativa na tabela oficial.',
            'complementares.max' => 'O número de CNAEs complementares excede o limite permitido.',
            'complementares.*.exists' => 'Há CNAE complementar inativo ou inexistente na seleção.',
            'complementares.*.distinct' => 'Há CNAE complementar duplicado na seleção.',
        ];
    }
}
