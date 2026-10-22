<?php

namespace App\Http\Requests\Gestao;

use App\Support\Settings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação do anexo da vistoria, de um arquivo (`file`) ou de vários de uma vez (`files`). Os tipos aceitos e o tamanho máximo são
 * PARAMETRIZADOS (HU-014) — chaves vistoria.anexos.* (banco→cache→config),
 * com efeito sem deploy. O anti-IDOR e a imutabilidade da ficha concluída são
 * enforced no controller.
 */
class InspectionAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var array<int, string> $mimes */
        $mimes = (array) Settings::get(
            'vistoria.anexos.mime_permitidos',
            config('viabiliza.vistoria.anexos.mime_permitidos', ['application/pdf', 'image/jpeg', 'image/png', 'image/webp']),
        );

        $maxMb = (int) Settings::get(
            'vistoria.anexos.max_mb',
            config('viabiliza.vistoria.anexos.max_mb', 10),
        );

        $maxKb = $maxMb * 1024;

        $regraDoArquivo = ['file', 'mimetypes:'.implode(',', $mimes), "max:{$maxKb}"];

        return [
            'file' => ['required_without:files', ...$regraDoArquivo],
            'files' => ['required_without:file', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', ...$regraDoArquivo],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Selecione o arquivo a anexar.',
            'file.file' => 'O anexo enviado é inválido.',
            'file.mimetypes' => 'O tipo do arquivo não é permitido.',
            'file.max' => 'O arquivo excede o tamanho máximo permitido.',
            'files.required_without' => 'Selecione o arquivo a anexar.',
            'files.max' => 'Envie no máximo 20 arquivos de uma vez.',
            'files.*.file' => 'Um dos anexos enviados é inválido.',
            'files.*.mimetypes' => 'O tipo de um dos arquivos não é permitido.',
            'files.*.max' => 'Um dos arquivos excede o tamanho máximo permitido.',
        ];
    }
}
