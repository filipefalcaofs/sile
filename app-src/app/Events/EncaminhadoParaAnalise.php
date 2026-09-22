<?php

namespace App\Events;

use App\Models\ViabilityRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento de domínio do EP10: a solicitação entrou em análise técnica humana
 * (em_analise). Espelha SolicitacaoProtocolada/ResultadoEmitido — carrega a
 * ViabilityRequest e é after-commit.
 *
 * Disparado pelo FluxoExpressoService::encaminharAnalise APÓS o commit da
 * transição → em_analise (wiring em 10-07) — só encaminhamentos efetivados
 * geram efeitos. Implementa ShouldDispatchAfterCommit como contrato/defesa: se
 * despachado de dentro de uma transação, só é observado após o commit (nunca em
 * rollback).
 *
 * A auditoria do encaminhamento NÃO depende deste evento: a transição síncrona
 * (ViabilityRequestStateMachine) já grava a timeline (RN-002) dentro da própria
 * transação, garantida mesmo se um listener falhar.
 *
 * É o seam que mantém o dispatcher (10-07) e o listener AUTO-DESCOBERTO da
 * pré-análise PreAnalisarProcesso (HU-140, 10-08 — roda o
 * SolicitacaoViabilityResolver e cria a ficha pré-preenchida) em planos
 * PARALELOS. O listener faz type-hint deste evento no handle; NÃO registrar via
 * Event::listen (auto-descoberta — lição das Fases 8/9).
 */
class EncaminhadoParaAnalise implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public ViabilityRequest $request) {}
}
