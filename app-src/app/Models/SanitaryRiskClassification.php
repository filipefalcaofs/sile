<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\RiscoSanitario;
use Database\Factories\SanitaryRiskClassificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Classificação de risco SANITÁRIO de uma subclasse CNAE (planilha VISA),
 * dimensão SEPARADA do risco municipal e em tabela própria (HU-019/HU-047/
 * HU-048). Ligada a uma versão de regra (rule_version_id) — dado versionado,
 * nunca código. cnae_code é FK lógica para Cnae.code (dígitos). A carga
 * oficial vem do RiscoSanitarioImportService (upsert sem model events —
 * auditoria é o log explícito do relatório no seeder); o CRUD manual dos
 * mantenedores (06-06) é auditado via HasAuditoria.
 */
#[Fillable([
    'rule_version_id',
    'cnae_code',
    'risco_sanitario',
    'macroarea',
    'autorizado_escritorio_virtual',
    'autorizado_mei',
    'exige_rt',
    'observacao',
])]
class SanitaryRiskClassification extends Model
{
    use HasAuditoria;

    /** @use HasFactory<SanitaryRiskClassificationFactory> */
    use HasFactory;

    /**
     * Nome explícito: a convenção pluralizaria a classe para
     * "sanitary_risk_classifications", mas a tabela é "risk_sanitary_classifications"
     * (alinhada à risk_classifications municipal e ao prefixo de domínio).
     */
    protected $table = 'risk_sanitary_classifications';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'risco_sanitario' => RiscoSanitario::class,
            'autorizado_escritorio_virtual' => 'boolean',
            'autorizado_mei' => 'boolean',
            'exige_rt' => 'boolean',
        ];
    }

    /**
     * Versão de regra (domínio risco_sanitario) a que esta classificação
     * pertence — viabiliza reprodução/auditoria da decisão por data.
     *
     * @return BelongsTo<RuleVersion, $this>
     */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }
}
