<?php

namespace App\Models;

use Database\Factories\TratamentoRegraRamoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ramo de decisão da planilha de tratamento (regra × resposta × faixa × tipo)
 * com o fluxo OFICIAL da seção "Fluxo Expresso / Semiexpresso" do texto da
 * regra — dado curado da fonte oficial, versionado com o domínio
 * RiscoTratamento. O fluxo do ramo vence a heurística nível+tipo
 * (TratamentoRamoResolver) e, quando expresso explícito, o gate de alto risco
 * do FluxoExpressoService (decisão SEDUR 22/09/2026 — a planilha prevalece
 * sobre a RN-041-B).
 */
#[Fillable(['rule_version_id', 'regra', 'pergunta', 'resposta', 'faixa', 'tipo_dirige', 'codigo_louos', 'fluxo', 'chave'])]
class TratamentoRegraRamo extends Model
{
    /** @use HasFactory<TratamentoRegraRamoFactory> */
    use HasFactory;

    protected $table = 'treatment_regra_ramos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'regra' => 'integer',
            'pergunta' => 'integer',
            'resposta' => 'boolean',
            'tipo_dirige' => 'boolean',
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
