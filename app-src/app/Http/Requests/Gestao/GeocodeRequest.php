<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Foundation\Http\FormRequest;

class GeocodeRequest extends FormRequest
{
    /**
     * PADRÃO CROSS-GUARD do território: o gate é o middleware
     * permission:consultar-territorio da rota — a permissão vive no guard web
     * (User::$guard_name fixo) e é resolvida de forma guard-agnóstica, mesmo
     * com o usuário autenticado no guard gestao. NÃO usar $this->user() sem
     * argumento aqui (cairia no guard default web e retornaria null numa sessão
     * só-gestao, produzindo um 403 falso). Por isso authorize() retorna true.
     */
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
            'address' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
