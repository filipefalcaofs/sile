<?php

namespace App\Http\Requests\Gestao;

use App\Enums\ViabilityRequestStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação da distribuição da caixa do setor (HU-080) — single e LOTE (RN-007):
 * um ou mais processos EM ANÁLISE são atribuídos a um analista. A autorização é
 * o middleware permission:distribuir-processos da rota. O vínculo do analista ao
 * setor de CADA processo (RN-004/HU-081) é regra de domínio do
 * DistribuicaoService — aqui valida-se a estrutura: ids existentes e em_analise,
 * e um analista existente.
 */
class DistribuirProcessoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aceita um id único (request_id) normalizando para a lista request_ids — o
     * lote é o caso geral; o single é um lote de um.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('request_ids') && $this->has('request_id')) {
            $this->merge(['request_ids' => [$this->input('request_id')]]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'request_ids' => ['required', 'array', 'min:1'],
            'request_ids.*' => [
                'integer',
                Rule::exists('viability_requests', 'id')->where('status', ViabilityRequestStatus::EmAnalise->value),
            ],
            'analista_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'request_ids' => 'processos',
            'request_ids.*' => 'processo',
            'analista_id' => 'analista',
        ];
    }
}
