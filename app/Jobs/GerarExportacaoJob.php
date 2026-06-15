<?php

namespace App\Jobs;

use App\Models\ExportFile;
use App\Models\User;
use App\Notifications\ExportacaoPronta;
use App\Services\Relatorios\Export\CsvExporter;
use App\Services\Relatorios\Export\PdfExporter;
use App\Services\Relatorios\Export\ReportFormatExporter;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\Export\XlsxExporter;
use App\Services\Relatorios\ReportFilters;
use App\Support\Audit\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Exportação ASSÍNCRONA acima do limiar (HU-131 RN-006), espelhando o
 * DecidirFluxoExpressoJob (tries/timeout/backoff/fila de config, failed()
 * auditado). Carrega só dados serializáveis — a CLASSE do source + o BAG de
 * filtros + formato + id do usuário; reconstrói a definition no worker via
 * `app($sourceClass)->definition(ReportFilters::fromArray($filtros))` (as
 * Closures builder/mapRow NUNCA são serializadas), reproduzindo EXATAMENTE o
 * conjunto filtrado (RN-005 no assíncrono).
 *
 * Anti-fachada (Pitfall 6): o ExportFile só é registrado APÓS a escrita
 * bem-sucedida do arquivo no disco não-público; um job que esgota as tentativas
 * AUDITA a falha (RN-002) e NÃO disponibiliza arquivo/link.
 */
class GerarExportacaoJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $timeout;

    /** @var array<int, int> */
    public array $backoff;

    /**
     * @param  class-string<ReportSource>  $sourceClass
     * @param  array<string, scalar>  $filtros
     */
    public function __construct(
        public readonly string $sourceClass,
        public readonly array $filtros,
        public readonly string $formato,
        public readonly ?int $userId = null,
    ) {
        $this->tries = (int) config('sile.relatorios.job.tries', 3);
        $this->timeout = (int) config('sile.relatorios.job.timeout', 300);
        $this->backoff = config('sile.relatorios.job.backoff', [30, 60, 120]);

        $fila = (string) config('sile.relatorios.job.fila', 'default');

        if ($fila !== '' && $fila !== 'default') {
            $this->onQueue($fila);
        }
    }

    public function handle(): void
    {
        $disk = $this->resolverDisco();
        $source = app($this->sourceClass);
        $definition = $source->definition(ReportFilters::fromArray($this->filtros));
        $driver = $this->driver($this->formato);

        $filename = $definition->fileName($this->formato);
        $relPath = 'relatorios/exportacoes/'.$filename;
        $storage = Storage::disk($disk);

        // openspout/CSV/PDF precisam de um caminho de filesystem real (Pitfall 4):
        // disco local grava direto pelo path(); disco remoto usa arquivo temporário
        // e depois Storage::put.
        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            $storage->makeDirectory(dirname($relPath));
            $driver->write($definition, $storage->path($relPath));
        } else {
            $temporario = tempnam(sys_get_temp_dir(), 'export');
            $driver->write($definition, $temporario);
            $storage->put($relPath, (string) file_get_contents($temporario));
            @unlink($temporario);
        }

        $export = ExportFile::create([
            'user_id' => $this->userId,
            'disk' => $disk,
            'path' => $relPath,
            'filename' => $filename,
            'format' => $this->formato,
            'row_count' => $definition->builder()->count(),
            'filtros' => $this->filtros,
        ]);

        if ($this->userId !== null) {
            User::query()->find($this->userId)?->notify(new ExportacaoPronta($export));
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(AuditService::class)->log(
            logName: 'relatorios',
            event: 'exporta-falha',
            description: "Falha ao gerar a exportação {$this->formato} (source {$this->sourceClass})",
            properties: [
                'source' => $this->sourceClass,
                'formato' => $this->formato,
                'user_id' => $this->userId,
                'filtros' => $this->filtros,
                'erro' => $exception?->getMessage(),
            ],
            result: 'falha',
            rulesVersion: 'relatorios-export-v1',
        );
    }

    /**
     * Disco de exportação parametrizado por constante técnica (config) — NUNCA
     * público: o arquivo pode conter PII (HU-131/LGPD), baixável só por URL
     * assinada (15-09).
     */
    private function resolverDisco(): string
    {
        $disk = (string) config('sile.relatorios.export.disk', 'local');

        if ($disk === 'public') {
            throw new RuntimeException('O disco de exportação não pode ser público (relatorios.export.disk) — o arquivo pode conter dados pessoais (HU-131/LGPD).');
        }

        return $disk;
    }

    private function driver(string $formato): ReportFormatExporter
    {
        return match ($formato) {
            'csv' => app(CsvExporter::class),
            'xlsx' => app(XlsxExporter::class),
            'pdf' => app(PdfExporter::class),
            default => throw new InvalidArgumentException("Formato de exportação não suportado: {$formato}."),
        };
    }
}
