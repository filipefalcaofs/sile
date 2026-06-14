<?php

namespace App\Models;

use Database\Factories\ViabilityQueryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico imutável de consultas prévias de viabilidade (HU-060). Cada
 * registro é um SNAPSHOT da consulta — entrada, resultado consolidado e versões
 * das regras da época — nunca editado. Não usa HasAuditoria: o snapshot já É o
 * registro auditável. user_id nullable (consulta anônima não grava histórico
 * pessoal — a regra de gravar só quando autenticado é do controller, 07-07).
 */
#[Fillable(['user_id', 'entry_type', 'input', 'result', 'rules_versions', 'resultado', 'ip_address', 'created_at'])]
class ViabilityQuery extends Model
{
    /** @use HasFactory<ViabilityQueryFactory> */
    use HasFactory;

    /**
     * Imutável: o snapshot grava created_at à mão e nunca recebe updated_at.
     */
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input' => 'array',
            'result' => 'array',
            'rules_versions' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Dono da consulta (nulo quando anônima).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Histórico SÓ do dono (precedente Company::countForUser — "Minhas
     * empresas"): o usuário autenticado vê apenas as próprias consultas.
     *
     * @param  Builder<ViabilityQuery>  $query
     * @return Builder<ViabilityQuery>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
