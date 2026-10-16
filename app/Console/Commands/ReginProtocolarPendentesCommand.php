<?php

namespace App\Console\Commands;

use App\Jobs\ProtocolarRecebimentoReginJob;
use App\Models\ReginRecebimento;
use Illuminate\Console\Command;

/**
 * Reenfileira a abertura do processo dos recebimentos REGIN que ficaram sem
 * processo (dado ausente no envelope, falha na fila). O job é idempotente: só
 * age em recebimento sem processo, então repetir o comando não duplica nada.
 */
class ReginProtocolarPendentesCommand extends Command
{
    protected $signature = 'regin:protocolar-pendentes
        {--protocolo=* : Limita a estes protocolos}
        {--dry-run : Lista os recebimentos, sem enfileirar}';

    protected $description = 'Reenfileira a abertura do processo dos recebimentos REGIN que ainda não têm processo';

    public function handle(): int
    {
        $protocolos = array_filter((array) $this->option('protocolo'));

        $recebimentos = ReginRecebimento::query()
            ->whereNull('viability_request_id')
            ->when($protocolos !== [], fn ($consulta) => $consulta->whereIn('protocolo', $protocolos))
            ->orderBy('id')
            ->get(['id', 'protocolo']);

        if ($recebimentos->isEmpty()) {
            $this->info('Nenhum recebimento sem processo.');

            return self::SUCCESS;
        }

        $this->info("{$recebimentos->count()} recebimento(s) sem processo.");

        foreach ($recebimentos as $recebimento) {
            $this->line($recebimento->protocolo);

            if (! $this->option('dry-run')) {
                ProtocolarRecebimentoReginJob::dispatch($recebimento->id);
            }
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry-run: nada foi enfileirado.');
        }

        return self::SUCCESS;
    }
}
