<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGeoServerLayerRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-territorio da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'label' => ($label = trim((string) $this->input('label'))) === '' ? null : $label,
        ];

        if ($this->has('ativo')) {
            $merge['ativo'] = $this->boolean('ativo');
        }

        $this->merge($merge);
    }

    /**
     * O par workspace+type_name é imutável na edição (padrão código/CNAE): os
     * valores enviados são ignorados por não constarem nas regras, logo nunca
     * chegam ao validated(). Trocar de camada = desativar a antiga e cadastrar
     * a nova, preservando a trilha.
     *
     * ativo é `sometimes`: um PUT que não envia o campo PRESERVA a situação
     * atual — não reativa uma camada desativada por acidente.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
            'ordem' => ['required', 'integer', 'min:0', 'max:65535'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label' => 'rótulo',
            'ordem' => 'ordem de consulta',
            'ativo' => 'situação',
        ];
    }
}
