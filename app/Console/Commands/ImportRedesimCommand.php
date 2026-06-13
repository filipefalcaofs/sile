<?php

namespace App\Console\Commands;

use App\Jobs\ImportRedesimJob;
use App\Services\RedesimImportService;
use Illuminate\Console\Command;
use JsonException;

/**
 * Importa dados empresariais no formato REDESIM a partir de um arquivo JSON
 * (HU-022). Útil para homologação manual com payloads de exemplo da SEDUR.
 *
 * O TRANSPORTE real do integrador (webservice/fila REGIN/JUCEB) é a Fase 13
 * (HU-103) — aqui o serviço é exercitado de verdade contra arquivos.
 */
class ImportRedesimCommand extends Command
{
    protected $signature = 'redesim:importar {arquivo : Caminho do arquivo JSON no formato REDESIM} {--queue : Processa em fila (job com retry/timeout) em vez de síncrono}';

    protected $description = 'Importa dados empresariais no formato REDESIM (HU-022) — transporte real do integrador é a Fase 13';

    public function handle(RedesimImportService $service): int
    {
        $path = (string) $this->argument('arquivo');

        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        if ($this->option('queue')) {
            ImportRedesimJob::dispatch($path);
            $this->info('Importação REDESIM enfileirada (job ImportRedesimJob).');

            return self::SUCCESS;
        }

        try {
            $relatorio = $service->import($path);
        } catch (JsonException $e) {
            $this->error('JSON inválido: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Importação REDESIM concluída.');
        $this->line("Lidos: {$relatorio['lidos']}");
        $this->line("Importados: {$relatorio['importados']}");
        $this->line("Atualizados: {$relatorio['atualizados']}");

        if ($relatorio['rejeitados'] !== []) {
            $this->newLine();
            $this->warn('Rejeitados:');
            foreach ($relatorio['rejeitados'] as $motivo) {
                $this->line("  - {$motivo}");
            }
        }

        if ($relatorio['avisos'] !== []) {
            $this->newLine();
            $this->warn('Avisos:');
            foreach ($relatorio['avisos'] as $aviso) {
                $this->line("  - {$aviso}");
            }
        }

        $todosRejeitados = $relatorio['lidos'] > 0
            && count($relatorio['rejeitados']) === $relatorio['lidos'];

        return $todosRejeitados ? self::FAILURE : self::SUCCESS;
    }
}
