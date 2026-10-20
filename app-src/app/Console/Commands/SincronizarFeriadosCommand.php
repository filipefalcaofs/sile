<?php

namespace App\Console\Commands;

use App\Services\Feriados\FeriadosApiClient;
use App\Services\Feriados\HolidaySyncService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Carrega os feriados de Salvador (nacionais, estaduais e municipais) da
 * FeriadosAPI. Sem argumento, sincroniza o ano corrente e o seguinte. Cada ano
 * é independente: a falha de um não impede o outro, mas o comando termina com
 * falha para o scheduler registrar.
 */
class SincronizarFeriadosCommand extends Command
{
    protected $signature = 'feriados:sincronizar {ano?* : Ano(s) a sincronizar; padrão: o corrente e o seguinte}';

    protected $description = 'Sincroniza os feriados de Salvador pela FeriadosAPI (idempotente; nunca sobrescreve cadastro existente)';

    public function handle(FeriadosApiClient $client, HolidaySyncService $service): int
    {
        if (! $client->isConfigured()) {
            $this->error('Token da FeriadosAPI ausente: defina FERIADOS_API_TOKEN no .env.');

            return self::FAILURE;
        }

        $years = $this->argument('ano') ?: [now()->year, now()->year + 1];
        $failed = false;

        foreach ($years as $year) {
            try {
                $report = $service->sync((int) $year);
            } catch (RuntimeException $e) {
                $this->error("{$year}: {$e->getMessage()}");
                $failed = true;

                continue;
            }

            $this->info("{$year}: {$report['criados']} criados, {$report['atualizados']} atualizados, {$report['ignorados']} já cadastrados.");
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
