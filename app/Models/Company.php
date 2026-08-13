<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\CompanySource;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Empresa (HU-023). CNPJ armazenado normalizado (14 chars uppercase, sem
 * máscara); a exibição usa o accessor formatted_cnpj. Auditoria automática
 * via HasAuditoria (RN-002). Relações com CNAEs (principal/secundários) e
 * vínculos de usuário (ativos/encerrados).
 */
#[Fillable(['cnpj', 'legal_name', 'trade_name', 'legal_nature_code', 'legal_nature', 'size_code', 'size', 'street', 'number', 'complement', 'neighborhood', 'city', 'state', 'zip_code', 'email', 'phone', 'source', 'redesim_protocol', 'redesim_synced_at'])]
class Company extends Model
{
    use HasAuditoria;

    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => CompanySource::class,
            'redesim_synced_at' => 'datetime',
        ];
    }

    /**
     * Contagem reutilizável das empresas com vínculo ATIVO do usuário —
     * consumida pela listagem (totalCompanies) e pelo painel do cidadão na
     * evolução (lacuna registrada no STATE).
     */
    public static function countForUser(User $user): int
    {
        return static::query()
            ->whereHas('links', fn ($q) => $q->where('user_id', $user->id)->whereNull('ended_at'))
            ->count();
    }

    /**
     * Todos os CNAEs vinculados (principal e secundários).
     *
     * @return BelongsToMany<Cnae, $this>
     */
    public function cnaes(): BelongsToMany
    {
        return $this->belongsToMany(Cnae::class, 'company_cnae')->withPivot('is_primary')->withTimestamps();
    }

    /**
     * CNAE principal (exatamente um, garantido na aplicação).
     *
     * @return BelongsToMany<Cnae, $this>
     */
    public function primaryCnae(): BelongsToMany
    {
        return $this->cnaes()->wherePivot('is_primary', true);
    }

    /**
     * Vínculos de usuário (ativos e encerrados — histórico preservado).
     *
     * @return HasMany<CompanyUser, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    /**
     * Vínculos ativos (sem encerramento).
     *
     * @return HasMany<CompanyUser, $this>
     */
    public function activeLinks(): HasMany
    {
        return $this->links()->whereNull('ended_at');
    }

    /**
     * CNPJ no formato 00.000.000/0000-00 (posicional — funciona com
     * o CNPJ alfanumérico de julho/2026).
     *
     * @return Attribute<string, never>
     */
    protected function formattedCnpj(): Attribute
    {
        return Attribute::make(
            get: fn () => preg_replace('/^(.{2})(.{3})(.{3})(.{4})(.{2})$/', '$1.$2.$3/$4-$5', $this->cnpj),
        );
    }
}
