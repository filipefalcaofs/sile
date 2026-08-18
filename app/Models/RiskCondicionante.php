<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\TipoRespostaCondicionante;
use Database\Factories\RiskCondicionanteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Condicionante de risco operacionalizada como PERGUNTA ao requerente: a
 * resposta reclassifica o risco (mecanismo "DI" do decreto/VISA;
 * HU-019/HU-048, RN-004/005/008). Ligada a uma versão de regra
 * (rule_version_id) — dado versionado, nunca código.
 *
 * O contrato de `regra_reclassificacao` (jsonb, cast 'array') é:
 *   {
 *     "resposta_gatilho": true,        // valor da resposta que dispara a reclassificação
 *     "reclassifica_para": "alto",     // nível-alvo (RiscoSanitario): baixo|medio|alto, ou null se indeterminado
 *     "fundamento": "Desde que ... Caso seja, será considerado Alto Risco."
 *   }
 * Quando a resposta do requerente == resposta_gatilho, o motor (06-05)
 * reclassifica o risco para reclassifica_para, registrando o fundamento na
 * decisão. reclassifica_para null = sem reclassificação automática (o motor
 * encaminha para análise com o fundamento).
 */
#[Fillable([
    'rule_version_id',
    'cnae_code',
    'pergunta',
    'tipo_resposta',
    'regra_reclassificacao',
    'texto_parecer',
])]
class RiskCondicionante extends Model
{
    use HasAuditoria;

    /** @use HasFactory<RiskCondicionanteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_resposta' => TipoRespostaCondicionante::class,
            'regra_reclassificacao' => 'array',
        ];
    }

    /**
     * Versão de regra (domínio risco_sanitario) a que esta condicionante
     * pertence — reprodução/auditoria da decisão por data.
     *
     * @return BelongsTo<RuleVersion, $this>
     */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }
}
