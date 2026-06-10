<?php

namespace App\Http\Requests;

use App\Models\Procuration;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProcurationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attorney_email' => ['required', 'email', 'exists:users,email'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * Bloqueios de negócio da HU-008 CA-03: auto-procuração e duplicidade
     * de procuração ativa para o mesmo par outorgante/procurador.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $attorney = User::query()->where('email', $this->input('attorney_email'))->first();

                if ($attorney === null) {
                    return;
                }

                if ($attorney->id === $this->user()->id) {
                    $validator->errors()->add('attorney_email', 'Você não pode outorgar procuração para si mesmo.');

                    return;
                }

                $hasActiveProcuration = Procuration::query()
                    ->active()
                    ->where('grantor_user_id', $this->user()->id)
                    ->where('attorney_user_id', $attorney->id)
                    ->exists();

                if ($hasActiveProcuration) {
                    $validator->errors()->add('attorney_email', 'Já existe uma procuração ativa para este procurador.');
                }
            },
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attorney_email.exists' => 'Não encontramos uma conta com este e-mail. Oriente o procurador a se cadastrar no SILE primeiro.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'attorney_email' => 'e-mail do procurador',
            'expires_at' => 'validade',
        ];
    }
}
