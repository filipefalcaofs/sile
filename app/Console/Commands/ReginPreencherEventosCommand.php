<?php

namespace App\Console\Commands;

use App\Models\ReginRecebimento;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginEventos;
use Illuminate\Console\Command;

/**
 * Grava os eventos da JUCEB dos processos do REGIN recebidos antes de o
 * protocolo passar a guardá-los. Idempotente: não repete o evento já gravado.
 */
class ReginPreencherEventosCommand extends Command
{
    protected $signature = 'regin:preencher-eventos';

    protected $description = 'Preenche os eventos da JUCEB (serviço) dos processos do REGIN já recebidos';

    public function handle(): int
    {
        $processos = 0;
        $eventos = 0;

        ViabilityRequest::query()
            ->whereNotNull('external_reference')
            ->orderBy('id')
            ->chunkById(200, function ($lote) use (&$processos, &$eventos): void {
                $recebimentos = ReginRecebimento::query()
                    ->whereIn('protocolo', $lote->pluck('external_reference'))
                    ->get()
                    ->keyBy('protocolo');

                foreach ($lote as $processo) {
                    $recebimento = $recebimentos->get($processo->external_reference);

                    if ($recebimento === null) {
                        continue;
                    }

                    $gravados = ReginEventos::gravar($processo, $recebimento);

                    if ($gravados > 0) {
                        $processos++;
                        $eventos += $gravados;
                    }
                }
            });

        $this->info("{$eventos} evento(s) gravado(s) em {$processos} processo(s).");

        return self::SUCCESS;
    }
}
