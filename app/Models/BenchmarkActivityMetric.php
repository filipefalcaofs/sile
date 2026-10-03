<?php

namespace App\Models;

use Database\Factories\BenchmarkActivityMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tempo de viabilidade por atividade econômica, em dois escopos: Salvador e o
 * total Brasil. A comparação entre os dois aponta em que atividades o
 * município é mais lento que o país.
 */
#[Fillable(['escopo', 'cnae', 'ano', 'mes', 'n', 'media', 'p50', 'p90'])]
class BenchmarkActivityMetric extends Model
{
    /** @use HasFactory<BenchmarkActivityMetricFactory> */
    use HasFactory;

    public const ESCOPO_SALVADOR = 'salvador';

    public const ESCOPO_BRASIL = 'brasil';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'mes' => 'integer',
            'n' => 'integer',
            'media' => 'float',
            'p50' => 'float',
            'p90' => 'float',
        ];
    }
}
