<?php

namespace App\Http\Requests\Gestao;

use App\Models\Parameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateParameterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A validação real é dinâmica (after): cada parâmetro carrega as próprias
     * validation_rules no catálogo (RN-007).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'value' => ['nullable', 'string'],
        ];
    }

    /**
     * Sensível com campo vazio significa "manter o valor atual" (RN-009) e
     * pula a validação dinâmica; nos demais casos as regras do próprio
     * registro decidem, com o nome amigável do parâmetro nas mensagens.
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

                /** @var Parameter $parameter */
                $parameter = $this->route('parameter');
                $value = $this->input('value');

                if ($parameter->sensitive && ($value === null || $value === '')) {
                    return;
                }

                $dynamic = validator(
                    ['value' => $value],
                    ['value' => $parameter->validation_rules],
                    [],
                    ['value' => $parameter->description],
                );

                foreach ($dynamic->errors()->get('value') as $message) {
                    $validator->errors()->add('value', $message);
                }
            },
        ];
    }
}
