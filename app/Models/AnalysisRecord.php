<?php

namespace App\Models;

use App\Enums\AnalysisRecordStatus;
use Database\Factories\AnalysisRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ficha de análise versionada (HU-135/140) — o coração da análise humana e a
 * fonte do parecer. Cada revisão é uma linha (unique viability_request_id+
 * revision); a revisão FINALIZADA é IMUTÁVEL (RN-003): nova mudança gera nova
 * revisão, nunca atualiza a finalizada. A rev 1 nasce pré-analisada pelo motor
 * (engine_snapshot/per_cnae) ou vazia quando o motor está indisponível
 * (engine_available=false, FA-01). per_cnae espelha a ficha do legado SAPS;
 * parking guarda vagas req×exigido×vistoria.
 */
#[Fillable([
    'viability_request_id',
    'revision',
    'status',
    'analyst_user_id',
    'engine_snapshot',
    'engine_rules_versions',
    'engine_available',
    'per_cnae',
    'conditions',
    'parking',
    'parecer',
    'is_virtual_office_hq',
    'finalized_at',
])]
class AnalysisRecord extends Model
{
    /** @use HasFactory<AnalysisRecordFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AnalysisRecordStatus::class,
            'engine_snapshot' => 'array',
            'engine_rules_versions' => 'array',
            'engine_available' => 'boolean',
            'per_cnae' => 'array',
            'conditions' => 'array',
            'parking' => 'array',
            'is_virtual_office_hq' => 'boolean',
            'finalized_at' => 'datetime',
        ];
    }

    public function isFinalizada(): bool
    {
        return $this->status === AnalysisRecordStatus::Finalizada;
    }

    /**
     * Processo ao qual a ficha pertence.
     *
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }

    /**
     * Analista responsável pela revisão; null nas revisões pré-analisadas pelo
     * motor antes de um humano assumir.
     *
     * @return BelongsTo<User, $this>
     */
    public function analyst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analyst_user_id');
    }

    /**
     * Divergências analista×motor registradas na finalização (HU-140 → HU-145).
     *
     * @return HasMany<AnalysisDivergence, $this>
     */
    public function divergences(): HasMany
    {
        return $this->hasMany(AnalysisDivergence::class);
    }
}
