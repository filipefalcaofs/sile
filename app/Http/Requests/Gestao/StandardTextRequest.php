<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação do CRUD da biblioteca de textos-padrão (HU-085). Serve store e
 * update; o versionamento por mudança de conteúdo (RN-005) é decidido no
 * controller, não aqui.
 */
class StandardTextRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-parametros da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza a categoria (trim) e assume ativo quando a situação não é
     * enviada — texto novo nasce disponível na biblioteca.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'category' => trim((string) $this->input('category')),
            'active' => $this->has('active') ? $this->boolean('active') : true,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string'],
            'active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category' => 'categoria',
            'content' => 'conteúdo',
            'active' => 'situação',
        ];
    }
}
