<?php

namespace App\Http\Requests\Gestao;

use App\Support\PerfisSolicitaveis;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccessRequestRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:acessar-gestao da rota.
     */
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
            'role' => ['required', 'string', Rule::in(PerfisSolicitaveis::roles()->pluck('name')->all())],
            'justificativa' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'role' => 'perfil',
            'justificativa' => 'justificativa',
        ];
    }
}
