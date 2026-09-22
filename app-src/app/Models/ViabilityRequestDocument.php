<?php

namespace App\Models;

use Database\Factories\ViabilityRequestDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anexo da solicitação (HU-066). Grava o disk DA ÉPOCA (nunca público), path,
 * hash sha256 e metadados — substituível antes do protocolo, imutável depois.
 */
#[Fillable(['requirement_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256', 'uploaded_by_user_id'])]
class ViabilityRequestDocument extends Model
{
    /** @use HasFactory<ViabilityRequestDocumentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class, 'viability_request_id');
    }

    /**
     * @return BelongsTo<DocumentRequirement, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(DocumentRequirement::class, 'requirement_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
