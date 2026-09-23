<?php

namespace App\Http\Requests\Gestao;

use App\Enums\ConclusionOutcome;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Conclusão de análise pela ficha ou pelo detalhe do processo. A autorização
 * é o middleware permission:analisar-processos. O resultado é obrigatório;
 * o parecer acompanha a tramitação quando o analista o registra.
 */
class ConcluirAnaliseRequest extends FormRequest
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
            'resultado' => ['required', Rule::enum(ConclusionOutcome::class)],
            'parecer' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'resultado' => 'resultado',
            'parecer' => 'parecer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resultado.required' => 'Escolha o resultado da conclusão de análise.',
        ];
    }
}
