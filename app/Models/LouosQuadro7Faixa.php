<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\LouosQuadro7FaixaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Faixa de área do Quadro 7 da LOUOS (HU-015/HU-038), ligada a uma versão de
 * regra (rule_version_id, domínio louos_quadro7) — dado versionado, nunca
 * código. cnae_code é FK lógica para Cnae.code (dígitos). area_max nula = sem
 * limite superior. Auditoria do CRUD manual via HasAuditoria; a carga oficial
 * (seed/import) audita pelo relatório explícito, sem model events.
 */
#[Fillable(['rule_version_id', 'cnae_code', 'grupo', 'subgrupo', 'area_min', 'area_max', 'observacao'])]
class LouosQuadro7Faixa extends Model
{
    use HasAuditoria;

    /** @use HasFactory<LouosQuadro7FaixaFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'area_min' => 'decimal:2',
            'area_max' => 'decimal:2',
        ];
    }

    /**
     * Versão de regra (domínio louos_quadro7) a que esta faixa pertence —
     * viabiliza reprodução/auditoria da decisão por data.
     *
     * @return BelongsTo<RuleVersion, $this>
     */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }
}
