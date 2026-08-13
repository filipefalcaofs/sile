<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Identificação territorial por ponto (HU-030/HU-036). PADRÃO CROSS-GUARD do
 * território (04-03): o gate é o middleware permission:consultar-territorio da
 * rota — a permissão vive no guard web (User::$guard_name fixo) e é resolvida
 * de forma guard-agnóstica mesmo com o usuário no guard gestao. NÃO usar
 * $this->user() sem argumento aqui (cairia no guard web e daria 403 falso numa
 * sessão só-gestao); por isso authorize() retorna true.
 */
class IdentifyTerritoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
