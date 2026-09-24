<?php

namespace App\Http\Requests\Gestao;

use App\Enums\AuthType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateUserAuthTypeRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-usuarios da rota.
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
            'auth_type' => ['required', new Enum(AuthType::class)],
            'ad_username' => [
                'required_if:auth_type,ad',
                'prohibited_if:auth_type,local',
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'ad_username')->ignore($this->route('user')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'auth_type' => 'tipo de autenticação',
            'ad_username' => 'conta AD',
        ];
    }
}
