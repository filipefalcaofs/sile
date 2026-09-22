<?php

namespace App\Http\Requests\Gestao;

use App\Models\Sector;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação do CRUD de setores (HU-138). Serve store e update: no update o
 * unique do nome ignora o próprio registro (reenviar o mesmo nome é válido).
 */
class SectorRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-setores da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza o nome (trim) e assume ativo quando a situação não é enviada —
     * setor novo nasce recebendo distribuições.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'active' => $this->has('active') ? $this->boolean('active') : true,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $sector = $this->route('sector');
        $sectorId = $sector instanceof Sector ? $sector->getKey() : $sector;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('sectors', 'name')->ignore($sectorId)],
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
            'active' => 'situação',
        ];
    }
}
