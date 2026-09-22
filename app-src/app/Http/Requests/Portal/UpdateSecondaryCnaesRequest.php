<?php

namespace App\Http\Requests\Portal;

use App\Models\Company;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSecondaryCnaesRequest extends FormRequest
{
    /**
     * Gerir CNAEs exige vínculo ATIVO do usuário efetivo (policy
     * manageCnaes, HU-026 CA-04). O 403 é auditado globalmente.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageCnaes', $this->route('company'));
    }

    /**
     * Conjunto exato de secundários: 'present' permite a remoção total
     * (array vazio); cada item deve ser CNAE ATIVO e sem duplicatas
     * (HU-026 CA-01/CA-03).
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'cnaes' => ['present', 'array'],
            'cnaes.*' => ['integer', 'distinct', Rule::exists('cnaes', 'id')->where('active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnaes.*.exists' => 'Há CNAE inativo ou inexistente na seleção.',
            'cnaes.*.distinct' => 'Há CNAE duplicado na seleção.',
        ];
    }

    /**
     * O conjunto de secundários nunca contém o principal atual da empresa
     * (HU-026 CA-03): a troca de principal é uma intenção distinta e tem
     * endpoint próprio.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Company $company */
                $company = $this->route('company');

                $primary = $company->primaryCnae()->first();

                if ($primary === null) {
                    return;
                }

                $selected = array_map('intval', (array) $this->input('cnaes', []));

                if (in_array($primary->id, $selected, true)) {
                    $validator->errors()->add('cnaes', 'O CNAE principal não pode ser incluído entre os secundários.');
                }
            },
        ];
    }
}
