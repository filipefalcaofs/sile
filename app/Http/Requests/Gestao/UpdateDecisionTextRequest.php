<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDecisionTextRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-parametros da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A key é imutável na edição (ligada ao motor): o valor enviado é
     * ignorado por não constar nas regras, logo nunca chega ao validated().
     * A descrição respeita o varchar(255) do Postgres real (lição da Fase 0).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'template' => ['required', 'string'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'template' => 'texto',
            'description' => 'descrição',
        ];
    }
}
