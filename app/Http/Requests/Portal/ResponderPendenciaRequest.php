<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação da resposta à pendência pelo portal (HU-084). A autorização (dono/
 * representante) e o vínculo da pendência com a solicitação (anti-IDOR) ficam no
 * controller; aqui só a resposta obrigatória, que vira a complementação gravada
 * em analysis_pendencies.response.
 */
class ResponderPendenciaRequest extends FormRequest
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
            'response' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'response.required' => 'Informe a resposta para o convite.',
            'response.max' => 'A resposta deve ter no máximo 5000 caracteres.',
        ];
    }
}
