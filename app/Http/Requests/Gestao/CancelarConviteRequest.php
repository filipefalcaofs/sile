<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação do cancelamento de convite (relatório SEDUR 2026-07-09): ao
 * cancelar, o analista DEVE registrar um parecer com o motivo. A autorização é
 * o middleware permission:analisar-processos da rota.
 */
class CancelarConviteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parecer' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parecer.required' => 'Informe o parecer com o motivo do cancelamento do convite.',
        ];
    }
}
