<?php

namespace App\Http\Requests\Gestao;

use App\Models\Cnae;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Código CNAE inválido do pedido e o código oficial que o substitui. Aceita a
 * máscara (4711-3/02) e guarda só os dígitos.
 */
class CorrigirCnaeInvalidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['codigo_atual', 'codigo_novo'] as $campo) {
            if (is_string($this->input($campo))) {
                $this->merge([$campo => preg_replace('/\D/', '', $this->input($campo))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ViabilityRequest $processo */
        $processo = $this->route('viabilityRequest');
        $invalidas = is_array($processo->comparacao_cnaes) ? array_values($processo->comparacao_cnaes['invalidas'] ?? []) : [];

        return [
            'codigo_atual' => ['required', 'string', Rule::in($invalidas)],
            'codigo_novo' => [
                'required',
                'string',
                Rule::exists(Cnae::class, 'code')->where(fn ($consulta) => $consulta->where('section_description', '!=', 'REGIN')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo_atual.in' => 'Esse código não está marcado como inválido neste processo.',
            'codigo_novo.exists' => 'Informe um código CNAE da lista oficial.',
        ];
    }
}
