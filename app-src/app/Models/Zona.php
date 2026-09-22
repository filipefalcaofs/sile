<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\ZonaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Zona urbanística da LOUOS (Lei nº 9.148/2016) — dado administrável
 * (HU-014, parametrização 3.3). A fonte de verdade é a própria LOUOS: a
 * carga inicial (ZonaSeeder) vem dos valores distintos de `zona` da versão
 * vigente do Quadro 10. O codigo é único e imutável na edição (padrão
 * código/CNAE) porque é a chave que as linhas do Quadro 10 referenciam; a
 * desativação NÃO remove a zona dos quadros vigentes (histórico) — só
 * bloqueia a publicação de novos rascunhos que a referenciem
 * (LouosDraftService::publicar). Auditada via HasAuditoria (RN-002).
 */
#[Fillable(['codigo', 'nome', 'macrozona', 'ativo'])]
class Zona extends Model
{
    use HasAuditoria;

    /** @use HasFactory<ZonaFactory> */
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
     * Zonas ativas — o universo que a validação de publicação do Quadro 10
     * aceita.
     *
     * @param  Builder<Zona>  $query
     * @return Builder<Zona>
     */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativo', true)->orderBy('codigo');
    }
}
