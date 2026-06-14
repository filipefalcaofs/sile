<?php

namespace App\Models;

use Database\Factories\TvlDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento TVL emitido (HU-132) — cada emissão/reimpressão do PDF da TVL é uma
 * linha auditada, vinculada à decisão (viability_decisions, reuso da Fase 9 —
 * fonte do parecer). disk/path apontam o arquivo no Storage (disco
 * parametrizado, NUNCA público); verification_code é o código de validação
 * único; generated_by/generated_at registram autor e momento. O download é por
 * rota assinada temporária (não vai ao cidadão).
 */
#[Fillable([
    'viability_decision_id',
    'disk',
    'path',
    'verification_code',
    'generated_by_user_id',
    'generated_at',
])]
class TvlDocument extends Model
{
    /** @use HasFactory<TvlDocumentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    /**
     * Decisão (deferida) que originou o documento — fonte do parecer.
     *
     * @return BelongsTo<ViabilityDecision, $this>
     */
    public function viabilityDecision(): BelongsTo
    {
        return $this->belongsTo(ViabilityDecision::class);
    }

    /**
     * Usuário que emitiu/reimprimiu o documento.
     *
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}
