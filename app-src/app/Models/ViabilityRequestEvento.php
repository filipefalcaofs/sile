<?php

namespace App\Models;

use Database\Factories\ViabilityRequestEventoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento do protocolo enviado pela JUCEB (PEV_COD_EVENTO / PEV_NOME_EVENTO),
 * como "Inscrição de primeiro estabelecimento". Um processo pode ter vários.
 */
#[Fillable(['viability_request_id', 'codigo', 'nome'])]
class ViabilityRequestEvento extends Model
{
    /** @use HasFactory<ViabilityRequestEventoFactory> */
    use HasFactory;

    protected $table = 'viability_request_eventos';

    /**
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }
}
