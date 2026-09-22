<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGeoServerLayerRequest extends FormRequest
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
        $this->merge([
            'workspace' => trim((string) $this->input('workspace')),
            'type_name' => trim((string) $this->input('type_name')),
            'label' => ($label = trim((string) $this->input('label'))) === '' ? null : $label,
            'ativo' => $this->has('ativo') ? $this->boolean('ativo') : true,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'workspace' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'type_name' => [
                'required',
                'string',
                'max:150',
                'regex:/^[A-Za-z0-9_]+$/',
                Rule::unique('geoserver_layers', 'type_name')
                    ->where('workspace', (string) $this->input('workspace')),
            ],
            'label' => ['nullable', 'string', 'max:255'],
            'ordem' => ['required', 'integer', 'min:0', 'max:65535'],
            'ativo' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'workspace' => 'workspace',
            'type_name' => 'nome da camada (FeatureType)',
            'label' => 'rótulo',
            'ordem' => 'ordem de consulta',
            'ativo' => 'situação',
        ];
    }
}
