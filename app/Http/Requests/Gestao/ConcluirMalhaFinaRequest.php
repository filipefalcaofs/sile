<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Conclusão da análise de malha fina pela caixa dedicada. A autorização é o
 * middleware permission:analisar-malha-fina da rota. A observação é OPCIONAL
 * — só quando há informação complementar da análise a registrar na trilha.
 */
class ConcluirMalhaFinaRequest extends FormRequest
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
            'observacao' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'observacao' => 'observação',
        ];
    }
}
