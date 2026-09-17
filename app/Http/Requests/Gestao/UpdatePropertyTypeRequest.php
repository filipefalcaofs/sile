<?php

namespace App\Http\Requests\Gestao;

use App\Rules\PropertyTypeAliasAvailable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePropertyTypeRequest extends FormRequest
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
            'active' => $this->has('active') ? $this->boolean('active') : true,
            'aliases' => array_values(array_filter(array_map(
                fn ($a) => trim((string) $a),
                (array) $this->input('aliases', []),
            ))),
        ]);
    }

    /**
     * O code é imutável na edição (padrão CPF/CNAE): o valor enviado é ignorado
     * por não constar nas regras, logo nunca chega ao validated().
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'drives_rule' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
            'aliases' => ['present', 'array'],
            'aliases.*' => ['string', 'max:255', new PropertyTypeAliasAvailable($this->route('propertyType')->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label' => 'rótulo',
            'drives_rule' => 'dirige regra',
            'active' => 'situação',
            'aliases' => 'grafias alternativas',
        ];
    }
}
