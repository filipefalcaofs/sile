<?php

namespace App\Http\Requests\Gestao;

use App\Enums\AnalysisStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação da mudança manual de status de análise. A autorização é o
 * middleware permission:analisar-processos da rota. `motivo` é obrigatório
 * quando o destino é convite_cancelado (parecer) ou quando é override.
 */
class AnalysisStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AnalysisStatus::class)],
            'motivo' => ['nullable', 'string', 'max:2000'],
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
