<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload dos Anexos A/B do Decreto 35.062/2021 para um rascunho versionado
 * (escritório virtual). A versão é nomeada por data com sufixo livre
 * (ev-anexos-{Y-m-d} por padrão); os dois CSVs seguem o cabeçalho
 * cnae_code,cnae_description do snapshot oficial.
 */
class PublicarAnexosEscritorioVirtualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'versao' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9\-]+$/'],
            'fonte' => ['required', 'string', 'max:255'],
            'anexo_a' => ['required', 'file', 'mimetypes:text/csv,text/plain', 'max:5120'],
            'anexo_b' => ['required', 'file', 'mimetypes:text/csv,text/plain', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'versao' => 'versão',
            'fonte' => 'fonte',
            'anexo_a' => 'CSV do Anexo A (sede)',
            'anexo_b' => 'CSV do Anexo B (abrigado)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'versao.regex' => 'A versão deve conter apenas letras minúsculas, números e hífens (ex.: ev-anexos-2026-09-18).',
            'anexo_a.mimetypes' => 'O CSV do Anexo A deve estar em formato CSV ou TXT.',
            'anexo_b.mimetypes' => 'O CSV do Anexo B deve estar em formato CSV ou TXT.',
            'anexo_a.max' => 'O CSV do Anexo A não pode ser maior que 5 MB.',
            'anexo_b.max' => 'O CSV do Anexo B não pode ser maior que 5 MB.',
        ];
    }
}
