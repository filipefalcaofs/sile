<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação do protocolo (HU-068). A autorização fina (dono + rascunho) é feita
 * no controller via Gate::authorize('protocol', ...). Aqui só a ciência opcional
 * "prosseguir mesmo assim" (HU-141 RN-002), quando a simulação tende ao
 * indeferimento.
 */
class ProtocolarSolicitacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'proceed_despite' => ['sometimes', 'boolean'],
        ];
    }
}
