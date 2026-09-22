<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreViabilityServiceTypeRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-tipos-servico da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza o code (trim) e define active=ativo por padrão quando o
     * campo não é enviado — tipo novo nasce disponível para seleção.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => trim((string) $this->input('code')),
            'active' => $this->has('active') ? $this->boolean('active') : true,
        ]);
    }

    /**
     * O formato oficial dos codes é pendência da SEDUR (seed mínimo no
     * fechamento da fase); aqui o code é livre, único e obrigatório.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:viability_service_types,code'],
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
            'code' => 'código',
            'name' => 'nome',
            'flow_hint' => 'pista de fluxo',
            'active' => 'situação',
        ];
    }
}
