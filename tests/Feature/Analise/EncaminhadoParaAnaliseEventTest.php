<?php

namespace Tests\Feature\Analise;

use App\Events\EncaminhadoParaAnalise;
use App\Models\ViabilityRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * EncaminhadoParaAnalise — o evento de domínio do EP10: a solicitação entrou
 * em análise técnica humana. Espelha SolicitacaoProtocolada/ResultadoEmitido: é
 * after-commit (ShouldDispatchAfterCommit, só efetiva efeitos após o commit do
 * encaminhamento) e carrega a ViabilityRequest. É o seam que mantém o dispatcher
 * (FluxoExpressoService::encaminharAnalise, wiring em 10-07) e o listener
 * AUTO-DESCOBERTO da pré-análise (PreAnalisarProcesso, 10-08) em planos
 * PARALELOS. Aqui só o evento; o listener NÃO é registrado via Event::listen
 * (auto-descoberta — lição das Fases 8/9). Este teste prova o CONTRATO do
 * payload, base do teste de CONTAGEM de listeners em 10-08.
 */
class EncaminhadoParaAnaliseEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_e_um_evento_after_commit(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();

        $this->assertInstanceOf(
            ShouldDispatchAfterCommit::class,
            new EncaminhadoParaAnalise($request),
        );
    }

    public function test_carrega_a_solicitacao(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();

        $event = new EncaminhadoParaAnalise($request);

        $this->assertTrue($event->request->is($request));
    }

    public function test_e_despachavel_carregando_a_solicitacao(): void
    {
        Event::fake([EncaminhadoParaAnalise::class]);

        $request = ViabilityRequest::factory()->protocoled()->create();

        EncaminhadoParaAnalise::dispatch($request);

        Event::assertDispatched(
            EncaminhadoParaAnalise::class,
            fn (EncaminhadoParaAnalise $event) => $event->request->is($request),
        );
    }
}
