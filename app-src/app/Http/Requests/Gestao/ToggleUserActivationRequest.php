<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ToggleUserActivationRequest extends FormRequest
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
        return [];
    }

    /**
     * Anti-lockout administrativo (HU-012 CA-03): o administrador nunca
     * inativa a própria conta — o sistema ficaria sem administração.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->route('user')->is($this->user())) {
                    $validator->errors()->add('user', __('Você não pode inativar a própria conta.'));
                }
            },
        ];
    }
}
