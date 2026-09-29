<?php

namespace App\Http\Requests\Gestao;

use App\Models\Parameter;
use App\Support\ApiIntegracaoCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateApiIntegracaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $campos = (array) $this->input('campos', []);
        $aba = ApiIntegracaoCatalog::find((string) $this->route('aba'));

        if ($aba === null) {
            return;
        }

        foreach ($aba['campos'] as $campo) {
            if (! array_key_exists($campo['id'], $campos)) {
                continue;
            }

            if ($campo['tipo'] === 'interruptor') {
                $campos[$campo['id']] = filter_var($campos[$campo['id']], FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            }

            if (in_array($campo['tipo'], ['senha', 'url'], true) && trim((string) $campos[$campo['id']]) === '') {
                $campos[$campo['id']] = null;
            }
        }

        $this->merge(['campos' => $campos]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $aba = ApiIntegracaoCatalog::find((string) $this->route('aba'));

        if ($aba === null) {
            return [];
        }

        $rules = [];

        foreach ($aba['campos'] as $campo) {
            $parameter = Parameter::query()->where('key', $campo['key'])->first();
            $fieldRules = $parameter?->validation_rules ?? ['nullable', 'string'];

            if ($campo['tipo'] === 'senha') {
                $fieldRules = ['nullable', 'string', 'max:255'];

                if ($campo['id'] === 'senha') {
                    $fieldRules[] = 'min:8';
                }
            }

            $rules['campos.'.$campo['id']] = $fieldRules;
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (ApiIntegracaoCatalog::find((string) $this->route('aba')) === null) {
                    $validator->errors()->add('aba', 'Integração desconhecida.');
                }
            },
        ];
    }
}
