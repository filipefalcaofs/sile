<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateViabilityServiceTypeRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-tipos-servico da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->has('active') ? $this->boolean('active') : true,
        ]);
    }

    /**
     * Apenas nome, pista de fluxo e situação são editáveis — o code é
     * imutável na edição (padrão CPF/CNAE: o valor enviado é ignorado por
     * não constar nas regras, logo nunca chega ao validated()).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'flow_hint' => ['nullable', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'flow_hint' => 'pista de fluxo',
            'active' => 'situação',
        ];
    }
}
