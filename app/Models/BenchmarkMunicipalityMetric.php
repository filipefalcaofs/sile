<?php

namespace App\Models;

use Database\Factories\BenchmarkMunicipalityMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tempo de viabilidade de endereço de um município numa competência, apurado
 * dos microdados da REDESIM — a régua pela qual Salvador é comparada ao país.
 */
#[Fillable([
    'uf',
    'municipio',
    'ano',
    'mes',
    'n',
    'media',
    'p50',
    'p75',
    'p90',
    'p95',
    'p99',
    'maximo',
])]
class BenchmarkMunicipalityMetric extends Model
{
    /** @use HasFactory<BenchmarkMunicipalityMetricFactory> */
    use HasFactory;

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
            'p75' => 'float',
            'p90' => 'float',
            'p95' => 'float',
            'p99' => 'float',
            'maximo' => 'float',
        ];
    }
}
