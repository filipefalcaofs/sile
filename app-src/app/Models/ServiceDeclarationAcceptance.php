<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro do aceite de uma declaração na versão exibida ao cidadão.
 */
#[Fillable(['service_declaration_id', 'viability_request_id', 'user_id', 'version', 'accepted_at'])]
class ServiceDeclarationAcceptance extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ServiceDeclaration, $this>
     */
    public function declaration(): BelongsTo
    {
        return $this->belongsTo(ServiceDeclaration::class, 'service_declaration_id');
    }

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
