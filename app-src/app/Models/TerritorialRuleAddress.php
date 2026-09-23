<?php

namespace App\Models;

use Database\Factories\TerritorialRuleAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Endereço vinculado a uma regra territorial: allow-list de Itaigara (CODLOG +
 * número de porta) e allow-lists de via das observações (a)/(b) do Quadro 10
 * (CODLOG, sem número). cod_log nullable: a fonte legal omite o código em uma
 * entrada (Hiperideal — OPEN-RT-2); nesse caso o casamento cai no nome
 * normalizado do logradouro.
 */
#[Fillable(['territorial_rule_id', 'rotulo', 'cod_log', 'logradouro_nome', 'numero_porta', 'bairro'])]
class TerritorialRuleAddress extends Model
{
    /** @use HasFactory<TerritorialRuleAddressFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TerritorialRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(TerritorialRule::class, 'territorial_rule_id');
    }
}
