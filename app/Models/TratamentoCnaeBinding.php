<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rule_version_id', 'cnae', 'regra', 'codigo_louos', 'perguntas', 'condicionantes'])]
class TratamentoCnaeBinding extends Model
{
    protected $table = 'treatment_cnae_bindings';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'perguntas' => 'array',
            'condicionantes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<RuleVersion, $this>
     */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }
}
