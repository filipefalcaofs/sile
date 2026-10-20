<?php

namespace App\Events;

use App\Models\AnalysisPendency;
use App\Models\ViabilityRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento de domínio do ciclo de pendência (HU-079/080 — PARCIAL): uma pendência
 * foi aberta na análise técnica e o processo entrou em em_pendencia. Carrega a
 * solicitação e a pendência (analysis_pendencies) recém-criada.
 *
 * O listener auto-descoberto NotificarPendencia (type-hint deste evento no
 * handle; NUNCA Event::listen) avisa o requerente por e-mail, in-app e WhatsApp,
 * conforme os toggles de canal. O evento só existe porque a abertura aconteceu
 * de verdade; nunca há "enviado" simulado.
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
