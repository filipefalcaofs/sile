<?php

namespace App\Http\Requests\Gestao;

use App\Enums\GeoLayerType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportGeoLayerRequest extends FormRequest
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
            'versao' => trim((string) $this->input('versao')),
            'origem' => trim((string) $this->input('origem')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxKb = (int) config('sile.geo.upload_max_mb', 20) * 1024;

        return [
            'tipo' => ['required', Rule::enum(GeoLayerType::class)],
            'versao' => ['required', 'string', 'max:100'],
            'origem' => ['nullable', 'string', 'max:255'],
            'arquivo' => ['required', 'file', "max:{$maxKb}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tipo' => 'tipo da camada',
            'versao' => 'versão',
            'origem' => 'origem',
            'arquivo' => 'arquivo GeoJSON',
        ];
    }
}
