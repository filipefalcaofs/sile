<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação do encaminhamento à malha fina (HU-136) — single e LOTE (RN-004): um
 * ou mais processos recebem o MESMO motivo. A autorização é o middleware
 * permission:encaminhar-malha-fina da rota. NÃO há restrição de status (RN-001 —
 * a malha fina é ortogonal ao status, atinge inclusive deferida); aqui valida-se
 * a estrutura: ids existentes e o motivo obrigatório (RN-002).
 */
class EncaminharMalhaFinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aceita um id único (request_id) normalizando para a lista request_ids — o
     * lote é o caso geral; o single é um lote de um (espelha DistribuirProcessoRequest).
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
            'request_ids.*' => ['integer', Rule::exists('viability_requests', 'id')],
            'motivo' => ['required', 'string', 'max:2000'],
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
            'motivo' => 'motivo',
        ];
    }
}
