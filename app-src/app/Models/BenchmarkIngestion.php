<?php

namespace App\Models;

use Database\Factories\BenchmarkIngestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Evidência de uma ingestão do benchmark federal: qual UF, qual competência,
 * de qual URL, com quantos bytes, qual hash e quantas linhas.
 *
 * É o que permite responder "de onde saiu este número" e o que sustenta a
 * degradação honesta: competência sem ingestão de sucesso em todas as UFs não
 * produz posição no ranking.
 */
#[Fillable([
    'uf',
    'ano',
    'mes',
    'status',
    'url',
    'bytes',
    'hash',
    'linhas_lidas',
    'linhas_descartadas',
    'motivo',
])]
class BenchmarkIngestion extends Model
{
    /** @use HasFactory<BenchmarkIngestionFactory> */
    use HasFactory;

    public const STATUS_SUCESSO = 'sucesso';

    public const STATUS_FALHA = 'falha';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'mes' => 'integer',
            'bytes' => 'integer',
            'linhas_lidas' => 'integer',
            'linhas_descartadas' => 'integer',
        ];
    }

    /**
     * @param  Builder<BenchmarkIngestion>  $query
     * @return Builder<BenchmarkIngestion>
     */
    public function scopeSucesso(Builder $query): Builder
    {
        // Sem linhas lidas não há dado: ingestões antigas gravadas assim (a
        // planilha chegou corrompida) precisam ser refeitas, não pulidas.
        return $query->where('status', self::STATUS_SUCESSO)->where('linhas_lidas', '>', 0);
    }
}
