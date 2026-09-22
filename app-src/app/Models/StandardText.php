<?php

namespace App\Models;

use Database\Factories\StandardTextFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Texto-padrão do parecer (HU-085) — biblioteca administrável: trechos pré-
 * aprovados que o analista insere ao redigir o parecer. category agrupa por
 * tema, active liga/desliga sem excluir e version evolui o texto mantendo o
 * histórico (dados versionados, não código).
 */
#[Fillable([
    'category',
    'content',
    'active',
    'version',
])]
class StandardText extends Model
{
    /** @use HasFactory<StandardTextFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'version' => 'integer',
        ];
    }
}
