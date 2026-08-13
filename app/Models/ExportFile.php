<?php

namespace App\Models;

use App\Support\Settings;
use Database\Factories\ExportFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
    use HasFactory, Prunable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_count' => 'integer',
            'filtros' => 'array',
            'created_at' => 'datetime',
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

    /**
     * Janela de retenção parametrizada (HU-014 / 15-15), espelhando o pruning de
     * access_logs (Fase 3.1). Usa Prunable (não MassPrunable) porque a poda
     * precisa disparar pruning() por registro para remover o arquivo físico —
     * o disco de exportação não pode acumular órfãos.
     *
     * @return Builder<ExportFile>
     */
    public function prunable(): Builder
    {
        return static::query()->where(
            'created_at',
            '<',
            now()->subDays((int) Settings::get(
                'relatorios.export.retencao_dias',
                config('sile.relatorios.export.retencao_dias', 7),
            )),
        );
    }

    /**
     * Remove o arquivo do Storage ANTES de apagar o registro (anti-órfão): a
     * retenção limpa banco E disco. O disco pode ser remoto (HU-131/LGPD,
     * nunca público); Storage::disk resolve o driver parametrizado.
     */
    protected function pruning(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }
}
