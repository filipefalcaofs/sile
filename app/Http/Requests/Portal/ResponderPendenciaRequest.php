<?php

namespace App\Http\Requests\Portal;

use App\Support\Settings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação da resposta à pendência pelo portal (HU-080). A autorização (dono/
 * representante) e o vínculo da pendência com a solicitação (anti-IDOR) ficam no
 * controller; aqui só a resposta obrigatória, que vira a complementação gravada
 * em analysis_pendencies.response.
 */
class ResponderPendenciaRequest extends FormRequest
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
        /** @var array<int, string> $mimes */
        $mimes = (array) config('viabiliza.solicitacao.anexos.mime_permitidos', ['application/pdf', 'image/jpeg', 'image/png']);

        $maxKb = (int) Settings::get(
            'solicitacao.anexos.max_mb',
            config('viabiliza.solicitacao.anexos.max_mb', 10),
        ) * 1024;

        return [
            'response' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimetypes:'.implode(',', $mimes), "max:{$maxKb}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'response.required' => 'Informe a resposta para o convite.',
            'response.max' => 'A resposta deve ter no máximo 5000 caracteres.',
            'attachment.file' => 'O anexo enviado é inválido.',
            'attachment.mimetypes' => 'O tipo do arquivo não é permitido. Envie PDF, JPG ou PNG.',
            'attachment.max' => 'O arquivo excede o tamanho máximo permitido.',
        ];
    }
}
