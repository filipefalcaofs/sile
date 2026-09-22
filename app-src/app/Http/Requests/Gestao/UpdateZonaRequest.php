<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateZonaRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-louos da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'macrozona' => ($macrozona = trim((string) $this->input('macrozona'))) === '' ? null : $macrozona,
        ];

        if ($this->has('ativo')) {
            $merge['ativo'] = $this->boolean('ativo');
        }

        $this->merge($merge);
    }

    /**
     * O codigo é imutável na edição (padrão código/CNAE): é a chave que as
     * linhas do Quadro 10 referenciam — o valor enviado é ignorado por não
     * constar nas regras, logo nunca chega ao validated(). Trocar o código =
     * desativar a zona e cadastrar a nova, preservando a trilha.
     *
     * ativo é `sometimes`: um PUT que não envia o campo PRESERVA a situação
     * atual — não reativa uma zona desativada por acidente.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'macrozona' => ['nullable', 'string', 'max:100'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nome' => 'nome',
            'macrozona' => 'macrozona',
            'ativo' => 'situação',
        ];
    }
}
