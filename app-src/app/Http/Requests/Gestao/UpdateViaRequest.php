<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateViaRequest extends FormRequest
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
        if ($this->has('ativo')) {
            $this->merge(['ativo' => $this->boolean('ativo')]);
        }
    }

    /**
     * O codigo é imutável na edição (padrão código/CNAE): é a chave que as
     * linhas do Quadro 11A referenciam — o valor enviado é ignorado por não
     * constar nas regras, logo nunca chega ao validated(). Trocar o código =
     * desativar a via e cadastrar a nova, preservando a trilha.
     *
     * ativo é `sometimes`: um PUT que não envia o campo PRESERVA a situação
     * atual — não reativa uma via desativada por acidente.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
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
            'ativo' => 'situação',
        ];
    }
}
