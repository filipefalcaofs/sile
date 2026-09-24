<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rule_version_id', 'chave', 'texto'])]
class TratamentoNota extends Model
{
    protected $table = 'treatment_notas';

    /**
     * @return BelongsTo<RuleVersion, $this>
     */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }
}
