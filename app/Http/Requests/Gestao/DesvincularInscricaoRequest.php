<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Desvinculação manual da inscrição da sede de escritório virtual (RN-EV-06).
 * Autorização = middleware permission:emitir-tvl da rota. Exige o motivo
 * (registrado na auditoria e na notificação aos abrigados).
 */
class DesvincularInscricaoRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Informe o motivo da desvinculação da inscrição.',
        ];
    }
}
