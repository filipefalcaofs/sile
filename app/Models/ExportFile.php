<?php

namespace App\Models;

use Database\Factories\ExportFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Arquivo de exportação gerado pelo GerarExportacaoJob (HU-131): o produto do
 * caminho ASSÍNCRONO, baixável por URL temporária assinada (rota nasce em 15-09)
 * e podado por retenção no scheduler (15-15). disk/path apontam o arquivo no
 * Storage (disco parametrizado, NUNCA público — LGPD); row_count é a contagem
 * real de linhas escritas; filtros guarda o bag aplicado (auditoria/reexecução).
 * Anti-fachada: o registro só é criado APÓS a escrita bem-sucedida do arquivo.
 */
#[Fillable([
    'user_id',
    'disk',
    'path',
    'filename',
    'format',
    'row_count',
    'filtros',
])]
class ExportFile extends Model
{
    /** @use HasFactory<ExportFileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_count' => 'integer',
            'filtros' => 'array',
        ];
    }

    /**
     * Usuário dono da exportação (destinatário da notificação de pronto).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
