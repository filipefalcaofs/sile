<?php

namespace App\Http\Requests\Portal;

use App\Support\Settings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação do anexo da solicitação (HU-066). A autorização fina (dono +
 * rascunho) é feita no controller via Gate::authorize('update', ...) — aqui só
 * a forma do arquivo. Os tipos aceitos (mimetypes) e o tamanho máximo são
 * PARAMETRIZADOS (HU-014) e lidos DINAMICAMENTE do catálogo (banco→cache→config),
 * com efeito sem deploy — espelha a validação dinâmica do [02-07]/[08-07].
 */
class StoreSolicitacaoDocumentoRequest extends FormRequest
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
            'solicitacao.anexos.mime_permitidos',
            config('sile.solicitacao.anexos.mime_permitidos', ['application/pdf', 'image/jpeg', 'image/png']),
        );

        $maxMb = (int) Settings::get(
            'solicitacao.anexos.max_mb',
            config('sile.solicitacao.anexos.max_mb', 10),
        );

        $maxKb = $maxMb * 1024;

        return [
            'file' => ['required', 'file', 'mimetypes:'.implode(',', $mimes), "max:{$maxKb}"],
            // Requisito atendido pelo anexo (opcional: anexo avulso é permitido).
            'requirement_id' => ['nullable', 'integer', Rule::exists('document_requirements', 'id')],
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
            'requirement_id.exists' => 'O requisito documental informado não existe.',
        ];
    }
}
