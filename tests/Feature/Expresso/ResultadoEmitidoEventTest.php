<?php

namespace Tests\Feature\Expresso;

use App\Events\ResultadoEmitido;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * ResultadoEmitido — o SEGUNDO evento de domínio do SILE (HU-076): a decisão do
 * fluxo expresso foi emitida. Espelha SolicitacaoProtocolada: é after-commit
 * (ShouldDispatchAfterCommit, só efetiva efeitos após o commit da transação da
 * decisão) e carrega a solicitação + a decisão imutável. Os efeitos (notificar
 * HU-077, comunicar Regin HU-104, enviar SEFAZ HU-110) são listeners
 * AUTO-DESCOBERTOS plugados na Wave 4 — aqui só o evento. A auditoria
 * autoritativa da decisão (HU-078) é SÍNCRONA na transação e NÃO depende deste
 * evento.
 */
class ResultadoEmitidoEventTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Solicitação protocolada + sua decisão imutável — o payload do evento.
     *
     * @return array{0: ViabilityRequest, 1: ViabilityDecision}
     */
    private function requestComDecisao(): array
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $decision = ViabilityDecision::factory()->create(['viability_request_id' => $request->id]);

        return [$request, $decision];
    }

    public function test_e_um_evento_after_commit(): void
    {
        [$request, $decision] = $this->requestComDecisao();

        $this->assertInstanceOf(
            ShouldDispatchAfterCommit::class,
            new ResultadoEmitido($request, $decision),
        );
    }

    public function test_carrega_a_solicitacao_e_a_decisao_imutaveis(): void
    {
        [$request, $decision] = $this->requestComDecisao();

        $event = new ResultadoEmitido($request, $decision);

        $this->assertTrue($event->request->is($request));
        $this->assertTrue($event->decision->is($decision));
    }

    public function test_e_despachavel_carregando_o_payload(): void
    {
        Event::fake([ResultadoEmitido::class]);

        [$request, $decision] = $this->requestComDecisao();

        ResultadoEmitido::dispatch($request, $decision);

        Event::assertDispatched(
            ResultadoEmitido::class,
            fn (ResultadoEmitido $event) => $event->request->is($request)
                && $event->decision->is($decision),
        );
    }
}
