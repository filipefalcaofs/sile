<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trava de inscrição imobiliária pela sede de escritório virtual ativa
 * (RN-EV-03). `ativoPara()` responde se uma inscrição já está vinculada a uma
 * sede — usado pelo M2 (abrigado) e pela desvinculação (M3).
 */
#[Fillable(['property_registration', 'sede_viability_request_id', 'active', 'locked_at', 'released_at'])]
class VirtualOfficeInscriptionLock extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['active' => 'boolean', 'locked_at' => 'datetime', 'released_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class, 'sede_viability_request_id');
    }

    public static function ativoPara(string $propertyRegistration): bool
    {
        return static::query()
            ->where('property_registration', $propertyRegistration)
            ->where('active', true)
            ->exists();
    }

    /**
     * Resolve o lock ATIVO da inscrição, com a sede e a decisão da sede (TVL)
     * já carregadas — para os consumidores M2 alcançarem o TVL do abrigado
     * sem N+1.
     */
    public static function sedeAtiva(string $propertyRegistration): ?self
    {
        return static::query()
            ->where('property_registration', $propertyRegistration)
            ->where('active', true)
            ->with('sede.decision')
            ->latest('id')
            ->first();
    }
}
