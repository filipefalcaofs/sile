<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReginIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'em_producao' => $this->boolean('em_producao'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'em_producao' => ['required', 'boolean'],
            'url_homologacao' => ['required', 'url'],
            'url_producao' => ['required', 'url'],
            'usuario' => ['required', 'string', 'max:255'],
            'senha' => ['nullable', 'string', 'min:8', 'max:255'],
        ];
    }
}
