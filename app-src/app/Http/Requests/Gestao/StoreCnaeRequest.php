<?php

namespace App\Http\Requests\Gestao;

use App\Enums\RiscoMunicipal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCnaeRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-cnaes da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aceita o código no formato oficial DDDD-D/SS ou já em dígitos:
     * a normalização acontece antes da validação.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['code' => preg_replace('/\D/', '', (string) $this->input('code'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'regex:/^\d{7}$/', 'unique:cnaes,code'],
            'description' => ['required', 'string', 'max:255'],
            'section_code' => ['required', 'string', 'max:1'],
            'section_description' => ['required', 'string', 'max:255'],
            'division_code' => ['required', 'string', 'max:2'],
            'division_description' => ['required', 'string', 'max:255'],
            'group_code' => ['required', 'string', 'max:5'],
            'group_description' => ['required', 'string', 'max:255'],
            'class_code' => ['required', 'string', 'max:7'],
            'class_description' => ['required', 'string', 'max:255'],
            'risco_municipal' => ['required', Rule::enum(RiscoMunicipal::class)],
            'exige_rt' => ['required', 'boolean'],
            'exige_rt_se_alto' => ['required', 'boolean'],
            'exige_fator_multiplicador' => ['required', 'boolean'],
            'exige_detalhamento_multiplicador' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'código',
            'description' => 'denominação',
            'section_code' => 'código da seção',
            'section_description' => 'descrição da seção',
            'division_code' => 'código da divisão',
            'division_description' => 'descrição da divisão',
            'group_code' => 'código do grupo',
            'group_description' => 'descrição do grupo',
            'class_code' => 'código da classe',
            'class_description' => 'descrição da classe',
            'risco_municipal' => 'grau de risco',
            'exige_rt' => 'exige responsável técnico',
            'exige_rt_se_alto' => 'exige RT apenas se alto risco',
            'exige_fator_multiplicador' => 'possui fator multiplicador',
            'exige_detalhamento_multiplicador' => 'exige detalhamento do multiplicador',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'O código deve ter 7 dígitos no padrão DDDD-D/SS.',
        ];
    }
}
