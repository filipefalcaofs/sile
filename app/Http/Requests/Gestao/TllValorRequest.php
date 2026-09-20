<?php

namespace App\Http\Requests\Gestao;

use App\Models\TllValor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação do CRUD da tabela de valores TLL por exercício (HU-071/HU-014).
 * Serve store e update: a chave (código TLL, exercício) é única e, no update, o
 * unique ignora o próprio registro. A autorização é o middleware
 * permission:manter-parametros da rota.
 */
class TllValorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza os booleanos (valor novo nasce ativo) e o exercício.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->has('active') ? $this->boolean('active') : true,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tllValor = $this->route('tllValor');
        $tllValorId = $tllValor instanceof TllValor ? $tllValor->getKey() : $tllValor;

        return [
            'codigo_tll' => [
                'required',
                'string',
                'max:20',
                Rule::unique('tll_valores', 'codigo_tll')
                    ->where('exercicio', $this->input('exercicio'))
                    ->ignore($tllValorId),
            ],
            'exercicio' => ['required', 'integer', 'min:2000', 'max:2200'],
            'valor' => ['required', 'numeric', 'min:0'],
            'taxa_servico' => ['nullable', 'numeric', 'min:0'],
            'codigo_tll_sefaz' => ['nullable', 'string', 'max:30'],
            'codigo_servico_sefaz' => ['nullable', 'string', 'max:30'],
            'servico_sefaz' => ['nullable', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'codigo_tll' => 'código TLL',
            'exercicio' => 'exercício',
            'valor' => 'valor',
            'taxa_servico' => 'taxa de serviço',
            'codigo_tll_sefaz' => 'código TLL SEFAZ',
            'codigo_servico_sefaz' => 'código de serviço SEFAZ',
            'servico_sefaz' => 'serviço SEFAZ',
            'active' => 'situação',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo_tll.unique' => 'Já existe um valor cadastrado para este código TLL neste exercício.',
        ];
    }
}
