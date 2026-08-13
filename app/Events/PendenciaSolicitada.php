<?php

namespace App\Events;

use App\Models\AnalysisPendency;
use App\Models\ViabilityRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento de domínio do ciclo de pendência (HU-083/084 — PARCIAL): uma pendência
 * foi aberta na análise técnica e o processo entrou em em_pendencia. Carrega a
 * solicitação e a pendência (analysis_pendencies) recém-criada.
 *
 * É o GANCHO HONESTO para a comunicação plena do EP11 (multicanal — WhatsApp/
 * in-app/templates + convite via Simplifica/Regin): hoje NENHUM listener está
 * pendurado aqui — o PendenciaService já envia o e-mail SIMPLES e real ao
 * requerente APÓS o commit. Quando o EP11 chegar, novos listeners AUTO-DESCOBERTOS
 * (type-hint deste evento no handle; NUNCA Event::listen) plugam os canais plenos
 * sem tocar o serviço. Nunca um "enviado" simulado: o evento só existe porque a
 * abertura aconteceu de verdade.
 *
 * Espelha ResultadoEmitido/EncaminhadoParaAnalise (Dispatchable +
 * ShouldDispatchAfterCommit, sem SerializesModels): o serviço o despacha após o
 * commit, e o contrato after-commit é a defesa de que efeitos só são observados
 * quando a transação efetiva.
 */
class PendenciaSolicitada implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public ViabilityRequest $request,
        public AnalysisPendency $pendency,
    ) {}
}
