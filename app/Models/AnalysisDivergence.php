<?php

namespace App\Models;

use Database\Factories\AnalysisDivergenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Divergência analista×motor (HU-140 → insumo do relatório HU-145): quando o
 * analista diverge do valor sugerido pelo motor num campo da ficha, registra a
 * diferença (suggested_value × final_value) com a justificativa.
 */
#[Fillable([
    'analysis_record_id',
    'cnae',
    'field',
    'suggested_value',
    'final_value',
    'justification',
])]
class AnalysisDivergence extends Model
{
    /** @use HasFactory<AnalysisDivergenceFactory> */
    use HasFactory;

    /**
     * Ficha (revisão) que originou a divergência.
     *
     * @return BelongsTo<AnalysisRecord, $this>
     */
    public function analysisRecord(): BelongsTo
    {
        return $this->belongsTo(AnalysisRecord::class);
    }
}
