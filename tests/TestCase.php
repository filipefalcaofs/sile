<?php

namespace Tests;

use App\Jobs\DecidirFluxoExpressoJob;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Queue;

abstract class TestCase extends BaseTestCase
{
    /**
     * Desligue (false) em testes que exercitam o worker REAL da fila
     * (queue:work/failed_jobs) — o fake parcial impediria o pop do worker.
     */
    protected bool $fakeExpressoDecisionJob = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // O gatilho do fluxo expresso (listener auto-descoberto AvaliarFluxoExpresso)
        // despacha o DecidirFluxoExpressoJob a CADA protocolo (SolicitacaoProtocolada,
        // EP08). Na suíte a fila é sync (phpunit.xml): o job rodaria INLINE e a
        // decisão — que em produção é ASSÍNCRONA, fora do request do protocolo —
        // mudaria o status logo após protocolar, acoplando testes de protocolo à
        // decisão (EP09). Fakeamos SÓ esse job por padrão; os demais jobs seguem
        // reais/sync. Quem exercita a decisão chama o serviço/job direto, dispara
        // o evento com Bus::fake (testes do gatilho) ou processa a fila.
        if ($this->fakeExpressoDecisionJob) {
            Queue::fake([DecidirFluxoExpressoJob::class]);
        }
    }
}
