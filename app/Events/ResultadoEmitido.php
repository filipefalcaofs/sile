<?php

namespace App\Events;

use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * SEGUNDO evento de domínio do SILE: o resultado do fluxo expresso foi emitido
 * (HU-076). Carrega a solicitação e a decisão imutável (ViabilityDecision) já
 * registrada — deferimento ou indeferimento.
 *
 * Disparado pelo FluxoExpressoService (09-05) APÓS o commit da transação da
 * decisão — só decisões efetivadas geram efeitos. Implementa
 * ShouldDispatchAfterCommit como contrato/defesa: despachado de dentro da
 * transação, só é observado após o commit (nunca em rollback).
 *
 * A auditoria autoritativa da decisão NÃO depende deste evento: a gravação
 * síncrona da viability_decisions + a auditoria (HU-078, RN-002/RN-005) ocorrem
 * dentro da própria transação, garantidas mesmo se um listener falhar. O evento
 * é APENAS a base desacoplada dos efeitos colaterais — listeners AUTO-DESCOBERTOS
 * (cada um faz type-hint deste evento no handle; NÃO registrar via Event::listen,
 * que duplicaria o registro) pendurados na Wave 4, sem tocar a decisão:
 *  - NotificarResultadoExpresso (HU-077) — e-mail ao cidadão, sem anexo de TVL;
 *  - ComunicarResultadoRegin (HU-104) — bloqueado (ReginParecerNotifier); o
 *    listener captura a exceção e audita a pendência de integração;
 *  - EnviarViabilidadeSefaz (HU-110) — bloqueado (SefazViabilidadeGateway), SÓ
 *    no deferimento; o listener captura a exceção e audita a pendência.
 */
class ResultadoEmitido implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public ViabilityRequest $request,
        public ViabilityDecision $decision,
    ) {}
}
