<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Encaminhamento à vistoria: o setor de vistoria é OBRIGATÓRIO (sem ele o
 * processo não chega à caixa de quem distribui) e o motivo também (RN-002,
 * mesmo padrão da malha fina).
 */
class EncaminharVistoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'setor_vistoria_id' => [
                'required',
                'integer',
                Rule::exists('sectors', 'id')->where('active', true),
            ],
            'motivo' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'setor_vistoria_id.required' => 'Selecione o setor de vistoria — sem ele o processo não chega à fila de distribuição.',
            'setor_vistoria_id.exists' => 'O setor de vistoria informado não existe ou está inativo.',
            'motivo.required' => 'Informe o motivo do encaminhamento à vistoria.',
            'motivo.min' => 'O motivo precisa de ao menos 3 caracteres.',
        ];
    }
}
