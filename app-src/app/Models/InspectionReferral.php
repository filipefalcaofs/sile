<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Encaminhamento à vistoria (handoff do analista ao setor de vistoria).
 * Append-only: registra a ORIGEM (setor/analista) para o retorno automático
 * na conclusão da ficha. A auditoria do ato é explícita (AuditService no
 * EncaminharVistoriaService) — o model não loga eventos.
 */
#[Fillable([
    'viability_request_id',
    'encaminhado_por_user_id',
    'setor_origem_id',
    'analista_origem_user_id',
    'setor_vistoria_id',
    'motivo',
])]
class InspectionReferral extends Model
{
    /**
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function encaminhadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encaminhado_por_user_id');
    }

    /**
     * @return BelongsTo<Sector, $this>
     */
    public function setorVistoria(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'setor_vistoria_id');
    }
}
