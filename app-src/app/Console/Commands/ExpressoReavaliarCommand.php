<?php

namespace App\Console\Commands;

use App\Enums\ViabilityRequestStatus;
use App\Jobs\DecidirFluxoExpressoJob;
use App\Models\ViabilityRequest;
use Illuminate\Console\Command;

/**
 * Rede de SEGURANÇA do fluxo expresso (não o gatilho principal): redespacha a
 * decisão das solicitações PROTOCOLADAS que ficaram SEM decisão (órfãs) — o
 * gatilho do protocolo falhou, a fila perdeu o job ou o worker caiu.
 *
 * Reusa o caminho REAL (DecidirFluxoExpressoJob → FluxoExpressoService), não
 * decide inline; como o serviço é idempotente (Cache::lock + re-check),
 * redespachar é seguro. No-op honesto quando não há órfãs (não finge trabalho).
 * Agendado com withoutOverlapping/onOneServer (routes/console.php).
 */
class ExpressoReavaliarCommand extends Command
{
    protected $signature = 'expresso:reavaliar
        {--limit= : Máximo de solicitações reenfileiradas nesta passada}';

    protected $description = 'Rede de segurança: redespacha a decisão das solicitações protocoladas que ficaram sem decisão (órfãs)';

    public function handle(): int
    {
        $query = ViabilityRequest::query()
            ->where('status', ViabilityRequestStatus::Protocolada)
            ->whereDoesntHave('decision')
            ->orderBy('id');

        $limit = $this->option('limit');

        if ($limit !== null && (int) $limit > 0) {
            $query->limit((int) $limit);
        }

        $orfas = $query->get();

        if ($orfas->isEmpty()) {
            $this->info('Nenhuma solicitação pendente de decisão.');

            return self::SUCCESS;
        }

        foreach ($orfas as $orfa) {
            DecidirFluxoExpressoJob::dispatch($orfa->id);
        }

        $this->info("Reenfileiradas {$orfas->count()} solicitação(ões) protocolada(s) sem decisão para reavaliação.");

        return self::SUCCESS;
    }
}
