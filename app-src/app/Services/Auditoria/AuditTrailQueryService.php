<?php

namespace App\Services\Auditoria;

use App\Models\AccessLog;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Construção da query da consulta unificada da trilha de auditoria (HU-100) e
 * do histórico de alterações (HU-098) direto sobre a espinha activity_log
 * (decisão do CONTEXT: activity_log JÁ É a trilha — consultar server-driven,
 * sem materializar nem denormalizar). Espelha o ProcessoQueryService: filtros
 * por when() (só entram quando informados), whereLike caseSensitive:false
 * (case-insensitive em PostgreSQL e SQLite), orderByDesc e eager-load de
 * causer/actingFor/subject para evitar N+1. A paginação e o CSV ficam no
 * controller; aqui só se monta o Builder reutilizável.
 *
 * `acessos()` é a fonte SECUNDÁRIA (HU-100): o histórico GLOBAL de access_logs
 * (login/logout/falha/bloqueio), unido na aplicação (não por UNION SQL) — por
 * isso devolve um Builder próprio de AccessLog.
 */
class AuditTrailQueryService
{
    /**
     * Builder filtrado da trilha (HU-100), do mais recente ao mais antigo. Cada
     * filtro só entra quando informado: período (created_at), usuário (causer
     * por id ou nome), entidade (subject_type/subject_id), ação (log_name/event)
     * e resultado (result).
     *
     * @param  array<string, mixed>  $filtros
     * @return Builder<Activity>
     */
    public function filtered(array $filtros): Builder
    {
        return Activity::query()
            ->with(['causer', 'actingFor', 'subject'])
            ->when($this->data($filtros, 'data_de'), fn (Builder $q, string $d) => $q->whereDate('created_at', '>=', $d))
            ->when($this->data($filtros, 'data_ate'), fn (Builder $q, string $d) => $q->whereDate('created_at', '<=', $d))
            ->when($this->inteiro($filtros, 'usuario_id'), fn (Builder $q, int $id) => $q->where('causer_id', $id))
            ->when($this->valor($filtros, 'usuario'), fn (Builder $q, string $v) => $q->whereHasMorph('causer', [User::class], fn ($c) => $c->whereLike('name', "%{$v}%", caseSensitive: false)))
            ->when($this->valor($filtros, 'entidade_tipo'), fn (Builder $q, string $v) => $q->whereLike('subject_type', "%{$v}%", caseSensitive: false))
            ->when($this->inteiro($filtros, 'entidade_id'), fn (Builder $q, int $id) => $q->where('subject_id', $id))
            ->when($this->valor($filtros, 'log_name'), fn (Builder $q, string $v) => $q->where('log_name', $v))
            ->when($this->valor($filtros, 'event'), fn (Builder $q, string $v) => $q->where('event', $v))
            ->when($this->valor($filtros, 'resultado'), fn (Builder $q, string $v) => $q->where('result', $v))
            ->orderByDesc('id');
    }

    /**
     * Variante "só alterações" (HU-098): os MESMOS filtros, recortando apenas os
     * registros com diff de dados (attribute_changes preenchido — gravado por
     * HasAuditoria nos eventos de mudança).
     *
     * @param  array<string, mixed>  $filtros
     * @return Builder<Activity>
     */
    public function apenasAlteracoes(array $filtros): Builder
    {
        return $this->filtered($filtros)->whereNotNull('attribute_changes');
    }

    /**
     * Fonte secundária (HU-100): histórico GLOBAL de access_logs
     * (login/logout/falha/bloqueio) — não por usuário (≠ AccessHistoryController),
     * com os filtros aplicáveis (período/usuário/evento), do mais recente ao mais
     * antigo. O merge com a trilha acontece na aplicação (UI/controller).
     *
     * @param  array<string, mixed>  $filtros
     * @return Builder<AccessLog>
     */
    public function acessos(array $filtros): Builder
    {
        return AccessLog::query()
            ->with('user:id,name')
            ->when($this->data($filtros, 'data_de'), fn (Builder $q, string $d) => $q->whereDate('created_at', '>=', $d))
            ->when($this->data($filtros, 'data_ate'), fn (Builder $q, string $d) => $q->whereDate('created_at', '<=', $d))
            ->when($this->inteiro($filtros, 'usuario_id'), fn (Builder $q, int $id) => $q->where('user_id', $id))
            ->when($this->valor($filtros, 'usuario'), fn (Builder $q, string $v) => $q->whereHas('user', fn ($u) => $u->whereLike('name', "%{$v}%", caseSensitive: false)))
            ->when($this->valor($filtros, 'event'), fn (Builder $q, string $v) => $q->where('event', $v))
            ->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function valor(array $filtros, string $chave): ?string
    {
        $valor = isset($filtros[$chave]) ? trim((string) $filtros[$chave]) : '';

        return $valor === '' ? null : $valor;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function inteiro(array $filtros, string $chave): ?int
    {
        $valor = $this->valor($filtros, $chave);

        return $valor !== null && ctype_digit($valor) ? (int) $valor : null;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function data(array $filtros, string $chave): ?string
    {
        $valor = $this->valor($filtros, $chave);

        return $valor !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1 ? $valor : null;
    }
}
