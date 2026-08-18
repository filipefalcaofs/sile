<?php

namespace App\Http\Requests\Gestao;

use App\Enums\RiscoSanitario;
use App\Enums\TipoRespostaCondicionante;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cadastro de uma condicionante-pergunta de risco (HU-019 RN-004). A
 * autorização é o middleware permission:manter-cnaes da rota. A condicionante
 * opera como pergunta booleana com regra de reclassificação opcional: quando a
 * resposta == resposta_gatilho, reclassifica o risco para reclassifica_para
 * (baixo/medio/alto) — null mantém indeterminado (motor encaminha à análise).
 */
class StoreRiscoCondicionanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza o CNAE para dígitos (aceita o formato oficial DDDD-D/SS) e
     * assume a pergunta booleana sim/não como padrão quando o tipo é omitido.
     */
    protected function prepareForValidation(): void
    {
        $merge = ['tipo_resposta' => $this->input('tipo_resposta', TipoRespostaCondicionante::BooleanoSimNao->value)];

        if ($this->filled('cnae_code')) {
            $merge['cnae_code'] = preg_replace('/\D/', '', (string) $this->input('cnae_code'));
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cnae_code' => ['nullable', 'string', 'regex:/^\d{7}$/'],
            'pergunta' => ['required', 'string', 'max:1000'],
            'tipo_resposta' => ['required', Rule::in([TipoRespostaCondicionante::BooleanoSimNao->value])],
            'regra_reclassificacao' => ['nullable', 'array'],
            'regra_reclassificacao.resposta_gatilho' => ['required_with:regra_reclassificacao', 'boolean'],
            'regra_reclassificacao.reclassifica_para' => ['nullable', Rule::enum(RiscoSanitario::class)],
            'regra_reclassificacao.fundamento' => ['nullable', 'string', 'max:2000'],
            'texto_parecer' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cnae_code' => 'código do CNAE',
            'pergunta' => 'pergunta',
            'tipo_resposta' => 'tipo de resposta',
            'regra_reclassificacao.resposta_gatilho' => 'resposta que reclassifica',
            'regra_reclassificacao.reclassifica_para' => 'nível de reclassificação',
            'regra_reclassificacao.fundamento' => 'fundamento',
            'texto_parecer' => 'texto do parecer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnae_code.regex' => 'O código do CNAE deve ter 7 dígitos no padrão DDDD-D/SS.',
        ];
    }
}
