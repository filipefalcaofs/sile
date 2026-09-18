<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRiskTriggerRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-gatilhos-risco da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * codigo e categoria são imutáveis (enum-bound, com comportamento no
     * motor): não constam nas regras, logo o valor enviado é descartado e
     * nunca chega ao validated() — padrão CPF/CNAE.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'motivo' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'titulo' => 'título',
            'motivo' => 'motivo',
        ];
    }
}
