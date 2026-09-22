<?php

namespace App\Listeners;

use App\Events\EncaminhadoParaAnalise;
use App\Services\Analise\PreAnaliseService;

/**
 * Gatilho da pré-análise pelo motor (HU-140): pendura no evento de domínio
 * EncaminhadoParaAnalise (Fase 9/10-03) e delega ao PreAnaliseService, que roda
 * o SolicitacaoViabilityResolver FRESCO e cria a analysis_records revisão 1
 * pré-preenchida (ou em modo manual na degradação FA-01).
 *
 * Registrado SÓ por auto-descoberta de eventos (type-hint do evento no handle):
 * NÃO registrar via Event::listen no AppServiceProvider — isso DUPLICARIA o
 * registro e a execução (lição das Fases 8/9 — RN-002, auditoria 2×). A
 * não-duplicação é travada por CONTAGEM nos testes (exatamente 1 listener por
 * EncaminhadoParaAnalise).
 *
 * Síncrono (espelha AvaliarFluxoExpresso, que também é um listener síncrono): a
 * pré-análise é idempotente, então reprocessar é seguro.
 */
class PreAnalisarProcesso
{
    public function __construct(private readonly PreAnaliseService $preAnalise) {}

    public function handle(EncaminhadoParaAnalise $event): void
    {
        $this->preAnalise->preAnalisar($event->request);
    }
}
