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
     * A fonte tem carga concluída ou ignorada (mesmo checksum) iniciada nos
     * últimos `$dias`. Falha e carga em andamento não contam.
     */
    public static function emDia(FonteEdificacao $fonte, int $dias): bool
    {
        return static::query()
            ->where('source', $fonte)
            ->whereIn('status', [self::CONCLUIDA, self::IGNORADA])
            ->where('started_at', '>=', now()->subDays($dias))
            ->exists();
    }

    /**
     * Encerra como falha as cargas da fonte em andamento há mais de `$horas`:
     * o processo morreu sem gravar o resultado e a transação foi desfeita.
     */
    public static function encerrarOrfas(FonteEdificacao $fonte, int $horas): int
    {
        return static::query()
            ->where('source', $fonte)
            ->where('status', self::EM_ANDAMENTO)
            ->where('started_at', '<', now()->subHours($horas))
            ->update([
                'status' => self::FALHOU,
                'finished_at' => now(),
                'error' => "Carga interrompida: ficou em andamento por mais de {$horas} h sem terminar.",
                'updated_at' => now(),
            ]);
    }

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
