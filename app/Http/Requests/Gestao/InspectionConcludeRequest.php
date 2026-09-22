<?php

namespace App\Http\Requests\Gestao;

/**
 * Conclusão da ficha de vistoria: mesmas regras do rascunho, mas o PARECER é
 * obrigatório — sem parecer a ficha não pode ser concluída (RN do formulário).
 */
class InspectionConcludeRequest extends InspectionUpdateRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'parecer' => ['required', 'string', 'min:3', 'max:10000'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'parecer.required' => 'O parecer da vistoria é obrigatório para concluir a ficha.',
            'parecer.min' => 'O parecer da vistoria precisa de ao menos 3 caracteres.',
        ]);
    }
}
