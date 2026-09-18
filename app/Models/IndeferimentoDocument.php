<?php

namespace App\Models;

use Database\Factories\IndeferimentoDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento de indeferimento fundamentado emitido (espelho do TvlDocument) —
 * cada emissão/reimpressão do PDF é uma linha auditada, vinculada à decisão
 * indeferida (viability_decisions — fonte da fundamentação). disk/path apontam
 * o arquivo no Storage (disco parametrizado, NUNCA público); verification_code
 * é o código de validação único; generated_by/generated_at registram autor e
 * momento. O download é por rota assinada temporária (não vai ao cidadão).
 */
#[Fillable([
    'viability_decision_id',
    'disk',
    'path',
    'verification_code',
    'generated_by_user_id',
    'generated_at',
])]
class IndeferimentoDocument extends Model
{
    /** @use HasFactory<IndeferimentoDocumentFactory> */
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
     * Decisão (indeferida) que originou o documento — fonte da fundamentação.
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
