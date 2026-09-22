<?php

namespace App\Models;

use Database\Factories\TvlSequenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Contador transacional do número de produto TVL, um por ano. Incrementado pelo
 * TvlNumberGenerator sob lockForUpdate — concorrência-segura no pgsql. Espelha
 * ProtocolSequence.
 */
#[Fillable(['year', 'last_number'])]
class TvlSequence extends Model
{
    /** @use HasFactory<TvlSequenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
