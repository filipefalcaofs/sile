<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação da resolução de um alerta de abuso (HU-149 RN-003): confirmar e
 * descartar exigem justificativa OBRIGATÓRIA, espelhando o motivo obrigatório da
 * malha fina — só espaços em branco também é vazio (normalizado no
 * prepareForValidation antes do required). A autorização é o middleware
 * permission:gerenciar-alertas-abuso da rota.
 */
class ResolverAbuseAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza a justificativa: só espaços vira vazio, então o required recusa
     * (espelha o motivoObrigatorio do MalhaFinaService).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('justification')) {
            $this->merge(['justification' => trim((string) $this->input('justification'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'justification' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'justification' => 'justificativa',
        ];
    }
}
