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
}
