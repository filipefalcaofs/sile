<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Embedding de um precedente (HU-142) — infra de RAG da Onda 3 (Fase 14). 1:1 com
 * a solicitação decidida; o vetor representa o RESUMO ANONIMIZADO (sem PII).
 *
 * A coluna `embedding` é driver-aware (vector(N) no pgsql, json no SQLite) e fica
 * FORA do fillable: é escrita via SQL pelo PrecedentIndexer (cast ::vector no
 * pgsql) — não por mass-assign nem cast do Eloquent, que não entende o tipo
 * vetorial. Os metadados (content_hash de idempotência e model) são fillable.
 */
#[Fillable(['viability_request_id', 'content_hash', 'model'])]
class AiPrecedentEmbedding extends Model
{
    /**
     * Precedente representado por este vetor.
     *
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }
}
