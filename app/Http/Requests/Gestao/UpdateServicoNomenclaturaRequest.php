<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateServicoNomenclaturaRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-nomenclatura-servicos da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'para' => trim((string) $this->input('para')),
            'exibir' => $this->boolean('exibir'),
        ]);
    }

    /**
     * O De é a chave do mapeamento e não muda: só Para e Exibir.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'para' => ['required', 'string', 'max:255'],
            'exibir' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'para' => 'nome exibido (Para)',
            'exibir' => 'exibir',
        ];
    }
}
