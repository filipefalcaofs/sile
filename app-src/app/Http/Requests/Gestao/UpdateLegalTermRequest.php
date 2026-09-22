<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLegalTermRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-parametros da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * O type é imutável na edição (padrão CPF/CNAE): o valor enviado é
     * ignorado por não constar nas regras, logo nunca chega ao validated().
     * version e published_at também nunca são editáveis pelo usuário.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'título',
            'content' => 'conteúdo',
        ];
    }
}
