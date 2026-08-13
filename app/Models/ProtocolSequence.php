<?php

namespace App\Models;

use Database\Factories\ProtocolSequenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Contador transacional do número de protocolo, um por ano. Incrementado pelo
 * ProtocolNumberGenerator sob lockForUpdate — concorrência-segura no pgsql.
 */
#[Fillable(['year', 'last_number'])]
class ProtocolSequence extends Model
{
    /** @use HasFactory<ProtocolSequenceFactory> */
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
