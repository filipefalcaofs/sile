<?php

namespace App\Http\Requests\Gestao;

use App\Services\Regin\ReginProtocoloCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimularProtocoloReginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $respostas = $this->input('respostas');

        if (! is_array($respostas)) {
            return;
        }

        $normalizadas = [];

        foreach ($respostas as $cnae => $mapa) {
            if (! is_array($mapa)) {
                continue;
            }

            foreach ($mapa as $numero => $valor) {
                $normalizadas[$cnae][$numero] = $this->paraBool($valor);
            }
        }

        $this->merge(['respostas' => $normalizadas]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $codigos = array_column(app(ReginProtocoloCatalog::class)->todos(), 'codigo');

        return [
            'codigo' => ['required', 'string', 'max:64', Rule::in($codigos)],
            'respostas' => ['nullable', 'array'],
            'respostas.*' => ['array'],
            'respostas.*.*' => ['boolean'],
            'zona' => ['nullable', 'string', 'max:64'],
            'via' => ['nullable', 'string', 'max:32'],
            'tipo_imovel' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.in' => 'Protocolo de validação desconhecido.',
        ];
    }

    private function paraBool(mixed $valor): bool
    {
        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }
}
