<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreZonaRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-louos da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => trim((string) $this->input('codigo')),
            'nome' => trim((string) $this->input('nome')),
            'macrozona' => ($macrozona = trim((string) $this->input('macrozona'))) === '' ? null : $macrozona,
            'ativo' => $this->has('ativo') ? $this->boolean('ativo') : true,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:50', 'unique:zonas,codigo'],
            'nome' => ['required', 'string', 'max:255'],
            'macrozona' => ['nullable', 'string', 'max:100'],
            'ativo' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'nome' => 'nome',
            'macrozona' => 'macrozona',
            'ativo' => 'situação',
        ];
    }
}
