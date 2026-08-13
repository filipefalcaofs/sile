<?php

namespace App\Console\Commands;

use App\Services\Relatorios\Export\CsvExporter;
use App\Services\Relatorios\Export\PdfExporter;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportFormatExporter;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\Export\Sources\ExpressoQuedaReportSource;
use App\Services\Relatorios\Export\Sources\SolicitacoesReportSource;
use App\Services\Relatorios\Export\XlsxExporter;
use App\Services\Relatorios\ReportFilters;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Comando de EVIDÊNCIA do contrato de exportação (HU-131 / 15-15): a partir do
 * conjunto filtrado de uma fonte, grava UM arquivo REAL de cada formato
 * (CSV/XLSX/PDF) num caminho de evidência e imprime o caminho + a contagem de
 * linhas. Prova a lógica de export de ponta a ponta (entrega-funcional,
 * anti-fachada): usa os MESMOS drivers que o GerarExportacaoJob (write(), o
 * caminho assíncrono), sobre a definition REAL do ReportSource — a contagem vem
 * do conjunto (builder()->count()), nunca é cravada. Conjunto vazio gera arquivo
 * só com cabeçalho (0 linhas de dado), nunca um número inventado.
 *
 * O disco é o parametrizado (relatorios.export.disk) — NUNCA público (LGPD).
 */
class RelatoriosExportarCommand extends Command
{
    protected $signature = 'relatorios:exportar
        {source=solicitacoes : Fonte do relatório (solicitacoes, quedas-expresso)}
        {--formato= : Formato único (csv|xlsx|pdf); vazio gera os três}';

    protected $description = 'Gera um arquivo real de cada formato (CSV/XLSX/PDF) de um relatório — evidência end-to-end do export (HU-131)';

    /**
     * Fontes reconstrutíveis só a partir do bag (sem estado de construtor) — as
     * seguras para o comando de evidência (o container as resolve via app()).
     *
     * @var array<string, class-string<ReportSource>>
     */
    private const SOURCES = [
        'solicitacoes' => SolicitacoesReportSource::class,
        'quedas-expresso' => ExpressoQuedaReportSource::class,
    ];

    public function handle(): int
    {
        $chave = (string) $this->argument('source');
        $sourceClass = self::SOURCES[$chave] ?? null;

        if ($sourceClass === null) {
            $this->error("Fonte de relatório desconhecida: {$chave}. Disponíveis: ".implode(', ', array_keys(self::SOURCES)).'.');

            return self::FAILURE;
        }

        $formatos = $this->resolverFormatos();

        if ($formatos === []) {
            return self::FAILURE;
        }

        $disk = (string) config('sile.relatorios.export.disk', 'local');

        if ($disk === 'public') {
            $this->error('O disco de exportação não pode ser público (relatorios.export.disk) — o arquivo pode conter dados pessoais (HU-131/LGPD).');

            return self::FAILURE;
        }

        $definition = app($sourceClass)->definition(ReportFilters::fromArray([]));
        $total = $definition->builder()->count();
        $storage = Storage::disk($disk);

        $this->newLine();
        $this->line("Relatório: {$definition->titulo}");
        $this->line("Registros no conjunto: {$total}");
        $this->newLine();

        foreach ($formatos as $formato) {
            $caminho = $this->gravar($definition, $formato, $disk, $storage);
            $this->line("{$formato}: {$caminho} ({$total} registros)");
        }

        return self::SUCCESS;
    }

    /**
     * Formatos a gerar: o --formato (validado) ou os três do contrato, sempre
     * recortados pelos formatos habilitados (relatorios.export.formatos_habilitados).
     *
     * @return list<string>
     */
    private function resolverFormatos(): array
    {
        $disponiveis = ['csv', 'xlsx', 'pdf'];

        $habilitados = array_values(array_intersect(
            (array) Settings::get(
                'relatorios.export.formatos_habilitados',
                config('sile.relatorios.export.formatos_habilitados', $disponiveis),
            ),
            $disponiveis,
        ));

        $pedido = $this->option('formato');

        if ($pedido === null || $pedido === '') {
            return $habilitados;
        }

        $pedido = (string) $pedido;

        if (! in_array($pedido, $habilitados, true)) {
            $this->error("Formato indisponível: {$pedido}. Habilitados: ".implode(', ', $habilitados).'.');

            return [];
        }

        return [$pedido];
    }

    /**
     * Grava o arquivo REAL pelo driver do formato (o mesmo write() do
     * GerarExportacaoJob) e devolve o caminho. Disco local grava direto pelo
     * path(); disco remoto usa arquivo temporário + Storage::put (Pitfall 4).
     */
    private function gravar(ReportDefinition $definition, string $formato, string $disk, Filesystem $storage): string
    {
        $driver = $this->driver($formato);
        $relPath = 'relatorios/evidencias/'.$definition->fileName($formato);

        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            $storage->makeDirectory(dirname($relPath));
            $driver->write($definition, $storage->path($relPath));

            return $storage->path($relPath);
        }

        $temporario = tempnam(sys_get_temp_dir(), 'export');
        $driver->write($definition, $temporario);
        $storage->put($relPath, (string) file_get_contents($temporario));
        @unlink($temporario);

        return $relPath;
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
