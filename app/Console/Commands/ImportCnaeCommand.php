<?php

namespace App\Console\Commands;

use App\Jobs\ImportCnaeJob;
use App\Services\CnaeImportService;
use App\Support\Audit\AuditService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Reimporta a estrutura oficial de CNAEs a partir de um CSV (HU-011). É o
 * caminho de reimportação oficial (hoje só havia o re-seed): em modo síncrono
 * importa e audita o relatório; com --queue despacha o ImportCnaeJob.
 */
class ImportCnaeCommand extends Command
{
    protected $signature = 'cnae:importar {arquivo : Caminho do CSV oficial CNAE-Subclasses 2.3} {--queue : Processa em fila (job com retry/timeout) em vez de síncrono}';

    protected $description = 'Reimporta a estrutura oficial de CNAEs a partir de um CSV (HU-011) — caminho de reimportação oficial';

    public function handle(CnaeImportService $service, AuditService $audit): int
    {
        $path = (string) $this->argument('arquivo');

        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        if ($this->option('queue')) {
            ImportCnaeJob::dispatch($path);
            $this->info('Importação CNAE enfileirada (job ImportCnaeJob).');

            return self::SUCCESS;
        }

        try {
            $report = $service->import($path);
        } catch (RuntimeException $e) {
            $this->error('Falha na importação CNAE: '.$e->getMessage());

            return self::FAILURE;
        }

        $audit->log(
            logName: 'cnaes',
            event: 'importacao-oficial',
            description: 'Reimportação da estrutura oficial CNAE-Subclasses 2.3 via comando',
            properties: $report,
            rulesVersion: 'cnae-subclasses-2.3',
        );

        $this->info('Importação CNAE concluída.');
        $this->line("Lidos: {$report['lidos']}");
        $this->line("Importados: {$report['importados']}");
        $this->line("Atualizados: {$report['atualizados']}");

        if ($report['rejeitados'] !== []) {
            $this->newLine();
            $this->warn('Rejeitados:');
            foreach ($report['rejeitados'] as $motivo) {
                $this->line("  - {$motivo}");
            }
        }

        return self::SUCCESS;
    }
}
