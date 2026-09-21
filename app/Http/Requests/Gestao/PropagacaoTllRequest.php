<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Geração/regeneração do rascunho do exercício TLL pelo fator do decreto.
 * Autorização: middleware manter-parametros da rota.
 */
class PropagacaoTllRequest extends FormRequest
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
            'exercicio_origem' => ['required', 'integer', 'min:2000', 'max:2200'],
            'exercicio_destino' => ['required', 'integer', 'min:2000', 'max:2200', 'gt:exercicio_origem'],
            'fator' => ['required', 'numeric', 'gt:0', 'lte:2'],
            'decreto' => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'exercicio_origem' => 'exercício de origem',
            'exercicio_destino' => 'exercício de destino',
            'fator' => 'fator',
            'decreto' => 'decreto',
        ];
    }
}
