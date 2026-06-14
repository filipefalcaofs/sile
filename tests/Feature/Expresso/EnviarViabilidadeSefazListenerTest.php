<?php

namespace Tests\Feature\Expresso;

use App\Events\ResultadoEmitido;
use App\Models\Activity;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Sefaz\SefazViabilidadeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Efeito desacoplado HU-110: o listener AUTO-DESCOBERTO EnviarViabilidadeSefaz
 * consome o ResultadoEmitido e SÓ envia a viabilidade à SEFAZ municipal quando a
 * decisão é DEFERIDA. O indeferimento NÃO é enviado (HU-134 RN-003) — ignorado
 * de forma auditável. Como o contrato está BLOQUEADO (Fase 13), o gateway padrão
 * (UnavailableSefazViabilidadeGateway) LANÇA SefazUnavailableException; o listener
 * CAPTURA e AUDITA a pendência (result 'bloqueado') — NUNCA finge o envio
 * (anti-fachada). A não-duplicação (auto-descoberta, NÃO Event::listen) é travada
 * por CONTAGEM das auditorias (lição da Fase 8 — RN-002).
 */
class EnviarViabilidadeSefazListenerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Solicitação protocolada + decisão DEFERIDA (default da factory) — o payload
     * do evento no caminho que envia à SEFAZ.
     *
     * @return array{0: ViabilityRequest, 1: ViabilityDecision}
     */
    private function deferida(): array
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $decision = ViabilityDecision::factory()->create(['viability_request_id' => $request->id]);

        return [$request, $decision];
    }

    /**
     * Solicitação protocolada + decisão INDEFERIDA — o caminho que NÃO envia à
     * SEFAZ (HU-134 RN-003).
     *
     * @return array{0: ViabilityRequest, 1: ViabilityDecision}
     */
    private function indeferida(): array
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $decision = ViabilityDecision::factory()->indeferida()->create(['viability_request_id' => $request->id]);

        return [$request, $decision];
    }

    private function auditoriasSefaz(string $result): int
    {
        return Activity::query()
            ->where('log_name', 'integracoes')
            ->where('event', 'sefaz-viabilidade')
            ->where('result', $result)
            ->count();
    }

    public function test_deferimento_com_integracao_bloqueada_audita_pendencia_sem_quebrar_o_fluxo(): void
    {
        // Binding default = UnavailableSefazViabilidadeGateway (lança
        // SefazUnavailableException — transmissão não ocorreu). O listener
        // captura e audita a pendência (anti-fachada): registra 'bloqueado'
        // exatamente 1× (CONTAGEM), nunca um sucesso fictício, e o disparo do
        // evento retorna sem propagar exceção (a decisão não falha pela
        // integração bloqueada).
        [$request, $decision] = $this->deferida();

        event(new ResultadoEmitido($request, $decision));

        $this->assertSame(1, $this->auditoriasSefaz('bloqueado'));
        $this->assertSame(0, $this->auditoriasSefaz('sucesso'));
    }

    public function test_deferimento_com_sefaz_disponivel_envia_e_audita_sucesso(): void
    {
        // Com um gateway disponível (fake), o listener LIGA de verdade: chama
        // sendViabilidade e audita 'sucesso'. Prova que o canal funciona ponta a
        // ponta — a Fase 13 só troca o binding por uma SEFAZ conveniada.
        $gateway = $this->spy(SefazViabilidadeGateway::class);
        [$request, $decision] = $this->deferida();

        event(new ResultadoEmitido($request, $decision));

        $gateway->shouldHaveReceived('sendViabilidade')->once();
        $this->assertSame(1, $this->auditoriasSefaz('sucesso'));
        $this->assertSame(0, $this->auditoriasSefaz('bloqueado'));
    }

    public function test_indeferimento_nao_aciona_a_sefaz_e_audita_ignorado(): void
    {
        // HU-134 RN-003: o indeferimento NÃO vai à SEFAZ. O listener nem toca o
        // gateway (zero chamadas) e registra que o envio não se aplica
        // ('ignorado') — toda saída deixa trilha (RN-002), sem fingir envio.
        $gateway = $this->spy(SefazViabilidadeGateway::class);
        [$request, $decision] = $this->indeferida();

        event(new ResultadoEmitido($request, $decision));

        $gateway->shouldNotHaveReceived('sendViabilidade');
        $this->assertSame(1, $this->auditoriasSefaz('ignorado'));
        $this->assertSame(0, $this->auditoriasSefaz('sucesso'));
        $this->assertSame(0, $this->auditoriasSefaz('bloqueado'));
    }
}
