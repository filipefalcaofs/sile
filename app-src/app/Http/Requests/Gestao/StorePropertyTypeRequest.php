<?php

namespace App\Http\Requests\Gestao;

use App\Rules\PropertyTypeAliasAvailable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePropertyTypeRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-tipos-imovel da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => trim((string) $this->input('code')),
            'active' => $this->has('active') ? $this->boolean('active') : true,
            'aliases' => array_values(array_filter(array_map(
                fn ($a) => trim((string) $a),
                (array) $this->input('aliases', []),
            ))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:property_types,code'],
            'label' => ['required', 'string', 'max:255'],
            'drives_rule' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
            'aliases' => ['present', 'array'],
            'aliases.*' => ['string', 'max:255', new PropertyTypeAliasAvailable],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'código',
            'label' => 'rótulo',
            'drives_rule' => 'dirige regra',
            'active' => 'situação',
            'aliases' => 'grafias alternativas',
        ];
    }
}
