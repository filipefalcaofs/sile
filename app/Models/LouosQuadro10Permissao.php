<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\Quadro10Permissao;
use Database\Factories\LouosQuadro10PermissaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permissão da atividade na zona segundo o Quadro 10 da LOUOS (HU-016/HU-039),
 * ligada a uma versão de regra (rule_version_id, domínio louos_quadro10) — dado
 * versionado. grupo_uso casa com o grupo do Quadro 7; condicionante_ref remete
 * à condicionante urbanística quando a permissão é condicionada. Auditoria do
 * CRUD manual via HasAuditoria.
 */
#[Fillable(['rule_version_id', 'zona', 'grupo_uso', 'subgrupo', 'permissao', 'condicionante_ref', 'base_legal', 'observacao'])]
class LouosQuadro10Permissao extends Model
{
    use HasAuditoria;

    /** @use HasFactory<LouosQuadro10PermissaoFactory> */
    use HasFactory;

    /**
     * A pluralização padrão do Eloquent geraria louos_quadro10_permissaos.
     */
    protected $table = 'louos_quadro10_permissoes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissao' => Quadro10Permissao::class,
        ];
    }

    /**
     * Versão de regra (domínio louos_quadro10) a que esta permissão pertence.
     *
     * @return BelongsTo<RuleVersion, $this>
     */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }
}
