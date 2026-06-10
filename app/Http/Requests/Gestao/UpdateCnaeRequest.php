<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCnaeRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-cnaes da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Apenas denominação e situação são editáveis — código e hierarquia
     * vêm da fonte oficial (import) ou do cadastro manual completo e são
     * imutáveis na edição (padrão CPF da Fase 1: valor enviado é ignorado).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'description' => 'denominação',
            'active' => 'situação',
        ];
    }
}
