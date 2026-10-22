<?php

namespace App\Http\Requests\Gestao;

use App\Models\Zona;
use App\Support\Louos\Quadro11aClasseVia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Zona e via escolhidas na ficha de análise. A zona vem do cadastro de zonas da
 * LOUOS e a via, das classes do Quadro 11A. A justificativa só é pedida na
 * gravação: a prévia é uma simulação.
 */
class AlterarZonaViaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'zona' => ['nullable', 'string', Rule::in(Zona::query()->where('ativo', true)->pluck('codigo')->all())],
            'via' => ['nullable', 'string', Rule::in(Quadro11aClasseVia::oficiais())],
            'justificativa' => [$this->isMethod('PATCH') ? 'required' : 'nullable', 'string', 'min:5', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'zona.in' => 'Escolha uma zona da LOUOS.',
            'via.in' => 'Escolha uma classe de via da LOUOS.',
            'justificativa.required' => 'Informe a justificativa da alteração.',
            'justificativa.min' => 'A justificativa precisa ter pelo menos 5 caracteres.',
        ];
    }
}
