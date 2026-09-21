<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\ViaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Classe de via da LOUOS (Lei nº 9.148/2016 — Quadro 11A) — dado administrável
 * (HU-014; relatório de usabilidade SEDUR 19/09/2026, item 07). A fonte de
 * verdade é a própria LOUOS: a carga inicial (ViaSeeder) vem dos valores
 * distintos de `classe_via` da versão vigente do Quadro 11A. O codigo é único
 * e imutável na edição porque é a chave que as linhas do Quadro 11A
 * referenciam; a desativação NÃO remove a via dos quadros vigentes
 * (histórico) — só bloqueia a publicação de novos rascunhos que a referenciem
 * (LouosDraftService::publicar). Auditada via HasAuditoria (RN-002).
 */
#[Fillable(['codigo', 'nome', 'ativo'])]
class Via extends Model
{
    use HasAuditoria;

    /** @use HasFactory<ViaFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    /**
     * Vias ativas — o universo que a validação de publicação do Quadro 11A
     * aceita.
     *
     * @param  Builder<Via>  $query
     * @return Builder<Via>
     */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativo', true)->orderBy('codigo');
    }
}
