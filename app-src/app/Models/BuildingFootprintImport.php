<?php

namespace App\Models;

use App\Enums\FonteEdificacao;
use Database\Factories\BuildingFootprintImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma carga de edificações complementares, com contadores e resultado.
 */
#[Fillable(['source', 'release', 'checksum', 'status', 'counters', 'started_at', 'finished_at', 'error'])]
class BuildingFootprintImport extends Model
{
    public const EM_ANDAMENTO = 'em_andamento';

    public const CONCLUIDA = 'concluida';

    public const FALHOU = 'falhou';

    public const IGNORADA = 'ignorada';

    /** @use HasFactory<BuildingFootprintImportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => FonteEdificacao::class,
            'counters' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
