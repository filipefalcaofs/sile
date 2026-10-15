<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Justificativa que o parecer leva à JUCEB, editada na tela do processo. A
 * autorização é o middleware permission:concluir-analise. O limite de 10.000
 * caracteres é o do campo JUSTIFICATIVA_ANALISE da PSCS.
 */
class AtualizarJustificativaReginRequest extends FormRequest
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
            'justificativa' => ['required', 'string', 'max:10000'],
            'reenviar' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'justificativa.required' => 'Informe a justificativa que segue à JUCEB.',
            'justificativa.max' => 'A justificativa aceita no máximo 10.000 caracteres.',
        ];
    }
}
