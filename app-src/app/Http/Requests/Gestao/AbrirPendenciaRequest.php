<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação da abertura de pendência (HU-083): a descrição do que o requerente
 * precisa complementar é obrigatória. A autorização é o middleware
 * permission:analisar-processos da rota; a precondição de estado (em_analise) é
 * regra de domínio do PendenciaService (CA-03), não validação de formulário.
 */
class AbrirPendenciaRequest extends FormRequest
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
            'descricao' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'descricao' => 'descrição',
        ];
    }
}
