<?php

namespace App\Models;

use Database\Factories\AnalysisPendencyAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento anexado pelo requerente na resposta do convite. Grava o disk da
 * época (parametrizado, nunca público), path, hash sha256 e metadados; o
 * acesso é sempre por streaming autenticado (LGPD).
 */
#[Fillable(['analysis_pendency_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256'])]
class AnalysisPendencyAttachment extends Model
{
    /** @use HasFactory<AnalysisPendencyAttachmentFactory> */
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
     * @return BelongsTo<AnalysisPendency, $this>
     */
    public function pendency(): BelongsTo
    {
        return $this->belongsTo(AnalysisPendency::class, 'analysis_pendency_id');
    }
}
