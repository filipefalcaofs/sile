<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação do cancelamento (HU-070). A autorização fina (dono + não decidido) é
 * feita no controller via Gate::authorize('cancel', ...). Aqui só o motivo
 * obrigatório do cancelamento — registrado na trilha (reason da transição) para
 * auditoria do porquê.
 */
class CancelarSolicitacaoRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Informe o motivo do cancelamento.',
            'reason.max' => 'O motivo do cancelamento deve ter no máximo 1000 caracteres.',
        ];
    }
}
