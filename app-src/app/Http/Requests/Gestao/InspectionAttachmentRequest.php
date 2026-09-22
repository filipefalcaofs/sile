<?php

namespace App\Http\Requests\Gestao;

use App\Support\Settings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação do anexo da vistoria. Os tipos aceitos e o tamanho máximo são
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
            config('sile.vistoria.anexos.mime_permitidos', ['application/pdf', 'image/jpeg', 'image/png', 'image/webp']),
        );

        $maxMb = (int) Settings::get(
            'vistoria.anexos.max_mb',
            config('sile.vistoria.anexos.max_mb', 10),
        );

        $maxKb = $maxMb * 1024;

        return [
            'file' => ['required', 'file', 'mimetypes:'.implode(',', $mimes), "max:{$maxKb}"],
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
        ];
    }
}
