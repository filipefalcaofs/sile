<?php

namespace App\Models;

use Database\Factories\TerritorialRuleCnaeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CNAE vinculado a uma regra territorial (RT-01) — código em 7 dígitos, sem
 * máscara (precedente do import de CNAE). A descrição oficial é a do cadastro
 * de CNAE; aqui só o código casa.
 */
#[Fillable(['territorial_rule_id', 'cnae'])]
class TerritorialRuleCnae extends Model
{
    /** @use HasFactory<TerritorialRuleCnaeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TerritorialRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(TerritorialRule::class, 'territorial_rule_id');
    }
}
