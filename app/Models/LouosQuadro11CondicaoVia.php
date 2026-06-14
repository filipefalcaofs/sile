<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\LouosQuadro11CondicaoViaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Condições de instalação pela via — Quadros 11 e 11A da LOUOS
 * (HU-017/HU-018/HU-040/HU-041), ligadas a uma versão de regra (rule_version_id)
 * cujo domínio (louos_quadro11 ou louos_quadro11a) distingue o quadro. classe_via
 * é a classificação viária da LOUOS (atributo pendente confirmação SEDUR);
 * condicoes é o conjunto livre de exigências pela via. Auditoria via HasAuditoria.
 */
#[Fillable(['rule_version_id', 'classe_via', 'grupo_uso', 'condicoes', 'base_legal', 'observacao'])]
class LouosQuadro11CondicaoVia extends Model
{
    use HasAuditoria;

    /** @use HasFactory<LouosQuadro11CondicaoViaFactory> */
    use HasFactory;

    /**
     * A pluralização padrão do Eloquent geraria louos_quadro11_condicao_vias.
     */
    protected $table = 'louos_quadro11_condicoes_via';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condicoes' => 'array',
        ];
    }

    /**
     * Versão de regra (domínio louos_quadro11 ou louos_quadro11a) a que esta
     * condição de via pertence.
     *
     * @return BelongsTo<RuleVersion, $this>
     */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }
}
