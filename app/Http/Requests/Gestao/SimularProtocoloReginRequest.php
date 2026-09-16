<?php

namespace App\Http\Requests\Gestao;

use App\Services\Regin\ReginProtocoloCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimularProtocoloReginRequest extends FormRequest
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
        $codigos = array_column(app(ReginProtocoloCatalog::class)->todos(), 'codigo');

        return [
            'codigo' => ['required', 'string', 'max:64', Rule::in($codigos)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.in' => 'Protocolo de validação desconhecido.',
        ];
    }
}
