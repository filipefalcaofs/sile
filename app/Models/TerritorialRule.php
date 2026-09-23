<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\TerritorialRuleEffect;
use App\Enums\TerritorialRuleType;
use Database\Factories\TerritorialRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Regra territorial (RT-01 a RT-05 — pacote normativo SEDUR 2026-08-28) como
 * dado versionado: pertence a uma rule_version do domínio `territorial` e o
 * motor a lê por (tipo, efeito, alvo). O alvo depende do tipo: cod_log +
 * CNAEs filhos (RT-01), camada WFS + filtro (poligonais), zona_alvo + vias
 * filhas (observações do Quadro 10). O texto legal do indeferimento vem de
 * decision_texts (administrável); o motivo de análise é o texto exato do
 * requisito. Auditoria via HasAuditoria (RN-002).
 */
#[Fillable([
    'rule_version_id', 'codigo', 'titulo', 'tipo', 'efeito',
    'cod_log', 'logradouro_nome', 'camada', 'camada_filtro', 'zona_alvo',
    'motivo_analise', 'decision_text_id', 'base_legal', 'ativo',
])]
class TerritorialRule extends Model
{
    use HasAuditoria;

    /** @use HasFactory<TerritorialRuleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TerritorialRuleType::class,
            'efeito' => TerritorialRuleEffect::class,
            'ativo' => 'boolean',
        ];
    }

    /**
     * Versão de regra (domínio territorial) a que esta regra pertence.
     *
     * @return BelongsTo<RuleVersion, $this>
     */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }

    /**
     * Texto legal do indeferimento (RT-01) — administrável em Textos
     * decisórios.
     *
     * @return BelongsTo<DecisionText, $this>
     */
    public function decisionText(): BelongsTo
    {
        return $this->belongsTo(DecisionText::class);
    }

    /**
     * CNAEs vinculados (RT-01).
     *
     * @return HasMany<TerritorialRuleCnae, $this>
     */
    public function cnaes(): HasMany
    {
        return $this->hasMany(TerritorialRuleCnae::class);
    }

    /**
     * Endereços vinculados (allow-list de Itaigara; allow-lists de via das
     * observações do Quadro 10).
     *
     * @return HasMany<TerritorialRuleAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(TerritorialRuleAddress::class);
    }
}
