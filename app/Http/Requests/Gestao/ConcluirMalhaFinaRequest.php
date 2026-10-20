<?php

namespace App\Http\Requests\Gestao;

use App\Enums\ConclusionOutcome;
use App\Http\Requests\Gestao\Concerns\ValidaTipoDeferimento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Conclusão da análise de malha fina pela caixa dedicada. A autorização é o
 * middleware permission:analisar-malha-fina da rota. O resultado é o mesmo da
 * conclusão de análise. A observação é opcional.
 */
class ConcluirMalhaFinaRequest extends FormRequest
{
    use ValidaTipoDeferimento;

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
            'observacao' => ['nullable', 'string', 'max:2000'],
        ] + $this->regrasTipoDeferimento();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'resultado' => 'resultado',
            'observacao' => 'observação',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resultado.required' => 'Escolha o resultado da conclusão de análise.',
        ] + $this->mensagensTipoDeferimento();
    }
}
