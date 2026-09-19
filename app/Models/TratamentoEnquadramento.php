<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rule_version_id',
    'cnae',
    'denominacao',
    'risco',
    'regra',
    'codigo_louos',
    'denominacao_louos',
    'subcategoria',
    'grupo',
    'ate_m2',
    'enquadramento2',
    'ate_m2_2',
    'enquadramento3',
    'acima_m2',
    'codigo_tll',
    'especificacao_tll',
    'classificacao',
])]
class TratamentoEnquadramento extends Model
{
    protected $table = 'treatment_enquadramentos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ate_m2' => 'decimal:2',
            'ate_m2_2' => 'decimal:2',
            'acima_m2' => 'decimal:2',
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
