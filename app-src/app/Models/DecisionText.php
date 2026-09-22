<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Texto decisório administrável (TVL, parecer, ficha do cidadão) — dado
 * administrável ligado ao motor pela chave estável: SEM create/delete/toggle
 * pela interface, porque a chave ausente quebra a emissão do documento. A
 * edição do template é auditada (HasAuditoria, RN-002) e invalida o cache do
 * DecisionTextCatalog na hora — efeito sem deploy.
 */
#[Fillable(['key', 'template', 'description'])]
class DecisionText extends Model
{
    use HasAuditoria;

    public const CACHE_KEY = 'sile.decision_texts.catalogo';

    protected static function booted(): void
    {
        $forget = fn () => Cache::forget(self::CACHE_KEY);
        static::saved($forget);
        static::deleted($forget);
    }
}
