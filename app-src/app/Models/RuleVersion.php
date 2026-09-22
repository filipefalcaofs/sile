<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use Carbon\CarbonInterface;
use Database\Factories\RuleVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cabeçalho genérico de uma regra como dado versionado (HU-019/HU-020/HU-053),
 * espelhando GeoLayer: cada (domain, version) é uma versão; valid_to nulo +
 * status vigente é a versão em uso. O rascunho coexiste com a vigente (também
 * com valid_to nulo) — por isso scopeVigente filtra status, diferença de
 * disciplina em relação ao GeoLayer. Auditoria automática via HasAuditoria
 * (RN-002). As tabelas tipadas por domínio (Fase 6) referenciam esta versão.
 */
#[Fillable([
    'domain',
    'version',
    'status',
    'valid_from',
    'valid_to',
    'source',
    'rules_version',
    'published_at',
    'created_by',
    'published_by',
])]
class RuleVersion extends Model
{
    use HasAuditoria;

    /** @use HasFactory<RuleVersionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'domain' => RuleDomain::class,
            'status' => RuleVersionStatus::class,
            'valid_from' => 'date',
            'valid_to' => 'date',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Autor do rascunho.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Publicador (quatro olhos) — distinto do autor em domínios sensíveis.
     *
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Versão vigente de um domínio (valid_to nulo E status vigente) — uso
     * operacional. Diferente do GeoLayer: o rascunho também tem valid_to nulo,
     * logo o filtro de status é obrigatório para não confundir rascunho com
     * vigente.
     *
     * @param  Builder<RuleVersion>  $query
     * @return Builder<RuleVersion>
     */
    public function scopeVigente(Builder $query, RuleDomain|string $domain): Builder
    {
        return $query
            ->where('domain', $domain instanceof RuleDomain ? $domain->value : $domain)
            ->whereNull('valid_to')
            ->where('status', RuleVersionStatus::Vigente->value);
    }

    /**
     * Versão que estava vigente numa data específica — reprodução/auditoria da
     * decisão (mesma lógica de datas do GeoLayer::scopeNaData).
     *
     * @param  Builder<RuleVersion>  $query
     * @return Builder<RuleVersion>
     */
    public function scopeNaData(Builder $query, RuleDomain|string $domain, CarbonInterface $date): Builder
    {
        return $query
            ->where('domain', $domain instanceof RuleDomain ? $domain->value : $domain)
            ->where('valid_from', '<=', $date)
            ->where(function (Builder $inner) use ($date) {
                $inner->whereNull('valid_to')->orWhere('valid_to', '>', $date);
            });
    }

    /**
     * Uma versão específica de um domínio (consulta por número de versão).
     *
     * @param  Builder<RuleVersion>  $query
     * @return Builder<RuleVersion>
     */
    public function scopeVersao(Builder $query, RuleDomain|string $domain, string $version): Builder
    {
        return $query
            ->where('domain', $domain instanceof RuleDomain ? $domain->value : $domain)
            ->where('version', $version);
    }
}
