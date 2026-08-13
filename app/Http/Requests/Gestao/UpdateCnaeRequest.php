<?php

namespace App\Http\Requests\Gestao;

use App\Enums\RiscoMunicipal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCnaeRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-cnaes da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Código e hierarquia vêm da fonte oficial (import) ou do cadastro manual
     * completo e são imutáveis na edição (padrão CPF da Fase 1: valor enviado
     * é ignorado). Denominação, situação, grau de risco e as flags de
     * RT/fator multiplicador são editáveis na ficha única do CNAE.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
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
            'description' => 'denominação',
            'active' => 'situação',
            'risco_municipal' => 'grau de risco',
            'exige_rt' => 'exige responsável técnico',
            'exige_rt_se_alto' => 'exige RT apenas se alto risco',
            'exige_fator_multiplicador' => 'possui fator multiplicador',
            'exige_detalhamento_multiplicador' => 'exige detalhamento do multiplicador',
        ];
    }
}
