<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-usuarios da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Apenas papéis existentes no guard web são vinculáveis (HU-012 CA-03):
     * estruturais da Fase 1 + customizados criados via HU-013.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'role' => 'papel',
        ];
    }
}
