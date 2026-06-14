<?php

namespace App\Listeners;

use App\Events\SolicitacaoProtocolada;
use App\Jobs\DecidirFluxoExpressoJob;

/**
 * Gatilho principal da decisão do fluxo expresso (HU-073/074/075): pendura no
 * PRIMEIRO evento de domínio (SolicitacaoProtocolada, Fase 8) e apenas DESPACHA
 * o DecidirFluxoExpressoJob — NÃO decide inline. Despachar (em vez de decidir
 * aqui) mantém o protocolo rápido e ganha a resiliência da fila (retry/backoff).
 *
 * Registrado SÓ por auto-descoberta de eventos (type-hint do evento no handle):
 * NÃO registrar via Event::listen no AppServiceProvider — isso DUPLICARIA o
 * registro e a execução (lição da Fase 8 — RN-002, auditoria 2×). A
 * não-duplicação é travada por CONTAGEM nos testes (1 job por protocolo).
 */
class AvaliarFluxoExpresso
{
    public function handle(SolicitacaoProtocolada $event): void
    {
        $pending = DecidirFluxoExpressoJob::dispatch($event->request->id);

        // Fila dedicada parametrizável (config/sile.php); 'default' usa a fila
        // padrão sem sobrescrever a conexão.
        $fila = (string) config('sile.expresso.fila', 'default');

        if ($fila !== '' && $fila !== 'default') {
            $pending->onQueue($fila);
        }
    }
}
