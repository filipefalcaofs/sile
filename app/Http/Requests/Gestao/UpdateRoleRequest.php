<?php

namespace App\Http\Requests\Gestao;

use App\Support\RoleProtection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-perfis da rota.
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
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id),
            ],
            'permissions' => ['array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    /**
     * Proteções dos papéis estruturais (HU-013 CA-03) e anti-lockout:
     * o administrador nunca perde o acesso à própria gestão.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            function (Validator $validator) use ($role) {
                if (RoleProtection::isProtected($role) && $this->input('name') !== $role->name) {
                    $validator->errors()->add('name', __('Perfis protegidos do sistema não podem ser renomeados.'));
                }

                if ($role->name === 'administrador' && ! in_array('acessar-gestao', (array) $this->input('permissions'), true)) {
                    $validator->errors()->add('permissions', __('O papel administrador não pode perder a permissão acessar-gestao.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'permissions' => 'permissões',
        ];
    }
}
