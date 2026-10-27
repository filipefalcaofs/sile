<?php

namespace App\Models;

use Database\Factories\AnalysisAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento que o analista anexa à análise do processo. Grava o disk DA ÉPOCA
 * (parametrizado, nunca público), path, sha256 e metadados — o acesso é sempre
 * por streaming autenticado (LGPD), nunca por URL pública.
 */
#[Fillable(['disk', 'path', 'original_name', 'mime_type', 'size', 'sha256', 'uploaded_by_user_id'])]
class AnalysisAttachment extends Model
{
    /** @use HasFactory<AnalysisAttachmentFactory> */
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
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
