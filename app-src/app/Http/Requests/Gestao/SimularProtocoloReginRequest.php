<?php

namespace App\Http\Requests\Gestao;

use App\Rules\RespostaPlanilhaValida;
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
                $normalizadas[$cnae][$numero] = $this->paraResposta($valor);
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
            'respostas.*.*' => [new RespostaPlanilhaValida],
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

    /**
     * Sim/Não continua booleano; opção vira inteiro e conjunto de opções, lista de inteiros.
     */
    private function paraResposta(mixed $valor): mixed
    {
        if (is_array($valor)) {
            return array_values(array_map(fn (mixed $item): int => (int) $item, $valor));
        }

        if (is_bool($valor) || in_array($valor, ['true', 'false', '0', 0, '1', 1], true)) {
            return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
        }

        return is_numeric($valor) ? (int) $valor : $valor;
    }
}
