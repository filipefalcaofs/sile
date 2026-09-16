<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Importação de CSV para rascunho de Quadro da LOUOS.
 * O arquivo CSV não deve exceder 5 MB (5120 KB).
 */
class ImportLouosCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quadro' => ['required', 'string', 'in:quadro7,quadro10,quadro11a'],
            'arquivo' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'quadro' => 'Quadro',
            'arquivo' => 'arquivo CSV',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'arquivo.mimes' => 'O arquivo deve estar no formato CSV ou TXT.',
            'arquivo.max' => 'O arquivo não pode ser maior que 5 MB.',
        ];
    }
}
