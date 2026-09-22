<?php

namespace App\Models;

use Database\Factories\InspectionAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anexo da ficha de vistoria (foto/documento de campo). Grava o disk DA ÉPOCA
 * (parametrizado, nunca público), path, hash sha256 e metadados — o acesso é
 * sempre por streaming autenticado (LGPD), nunca por URL pública.
 */
#[Fillable(['disk', 'path', 'original_name', 'mime_type', 'size', 'sha256', 'uploaded_by_user_id'])]
class InspectionAttachment extends Model
{
    /** @use HasFactory<InspectionAttachmentFactory> */
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
     * @return BelongsTo<Inspection, $this>
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
