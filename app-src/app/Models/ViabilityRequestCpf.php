<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CPF vinculado a um processo sem ser o solicitante: sócio do REGIN ou
 * solicitante declarado da empresa a constituir. Dá acesso de leitura ao
 * processo, nunca o torna requerente.
 */
#[Fillable(['viability_request_id', 'cpf', 'papel'])]
class ViabilityRequestCpf extends Model
{
    public const PAPEL_SOCIO = 'socio';

    public const PAPEL_SOLICITANTE = 'solicitante';

    /**
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }
}
