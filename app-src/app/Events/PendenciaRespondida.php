<?php

namespace App\Events;

use App\Models\AnalysisPendency;
use App\Models\ViabilityRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento de domínio do ciclo de pendência (HU-091/092): o requerente respondeu a
 * pendência pelo portal e o processo voltou de em_pendencia para em_analise
 * (análise reaberta). Carrega a solicitação e a pendência (analysis_pendencies)
 * respondida.
 *
 * É o GANCHO HONESTO para fechar o ciclo de comunicação que a Fase 10 não
 * fechava: o listener AUTO-DESCOBERTO NotificarRespostaPendencia (type-hint deste
 * evento no handle; NUNCA Event::listen) avisa o analista responsável
 * (assigned_user_id) de que a análise reabriu. Nunca um "enviado" simulado: o
 * evento só existe porque a resposta aconteceu de verdade.
 *
 * Espelha PendenciaSolicitada/ResultadoEmitido (Dispatchable +
 * ShouldDispatchAfterCommit, sem SerializesModels): o serviço o despacha após o
 * commit, e o contrato after-commit é a defesa de que efeitos só são observados
 * quando a transação efetiva (em rollback nada dispara).
 */
class PendenciaRespondida implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public ViabilityRequest $request,
        public AnalysisPendency $pendency,
    ) {}
}
