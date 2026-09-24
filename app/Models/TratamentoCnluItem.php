<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rule_version_id', 'codigo', 'texto'])]
class TratamentoCnluItem extends Model
{
    protected $table = 'treatment_cnlu_itens';

    /**
     * @return BelongsTo<RuleVersion, $this>
     */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }
}
