<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\RiscoMunicipal;
use Database\Factories\RiskClassificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Classificação de risco MUNICIPAL de uma subclasse CNAE (Decreto nº
 * 32.636/2020), ligada a uma versão de regra (rule_version_id) — dado
 * versionado, nunca código (HU-020/HU-047). cnae_code é FK lógica para
 * Cnae.code (dígitos). A carga oficial vem do RiscoMunicipalImportService
 * (sem model events — auditoria é o log explícito do relatório no seeder); o
 * CRUD manual dos mantenedores (06-06) é auditado via HasAuditoria.
 */
#[Fillable(['rule_version_id', 'cnae_code', 'risco_municipal', 'condicionantes', 'observacao'])]
class RiskClassification extends Model
{
    use HasAuditoria;

    /** @use HasFactory<RiskClassificationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'risco_municipal' => RiscoMunicipal::class,
            'condicionantes' => 'array',
        ];
    }

    /**
     * Versão de regra (domínio risco_municipal) a que esta classificação
     * pertence — viabiliza reprodução/auditoria da decisão por data.
     *
     * @return BelongsTo<RuleVersion, $this>
     */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }
}
