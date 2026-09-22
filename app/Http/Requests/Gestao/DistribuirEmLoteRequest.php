<?php

namespace App\Http\Requests\Gestao;

use App\Enums\ViabilityRequestStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação estrutural da distribuição em lote entre vários analistas (Fase 3):
 * uma lista de alocações {analista, processos}. A autorização é o middleware
 * permission:distribuir-processos da rota. O escopo de setor do ator, a
 * ausência de sobreposição e a atomicidade são regra do controller/serviço.
 */
class DistribuirEmLoteRequest extends FormRequest
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
            'alocacoes' => ['required', 'array', 'min:1'],
            'alocacoes.*.analista_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'alocacoes.*.request_ids' => ['required', 'array', 'min:1'],
            'alocacoes.*.request_ids.*' => [
                'integer',
                Rule::exists('viability_requests', 'id')->where('status', ViabilityRequestStatus::EmAnalise->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'alocacoes' => 'alocações',
            'alocacoes.*.analista_id' => 'analista',
            'alocacoes.*.request_ids' => 'processos',
        ];
    }
}
