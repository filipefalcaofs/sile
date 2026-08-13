<?php

namespace Tests\Feature\Expresso;

use App\Enums\DecisionOutcome;
use App\Events\ResultadoEmitido;
use App\Models\Activity;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginParecerNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comunicação do parecer ao Regin/Junta (HU-104): o listener AUTO-DESCOBERTO
 * ComunicarResultadoRegin pendura no SEGUNDO evento de domínio (ResultadoEmitido)
 * e tenta comunicar o parecer — deferido OU indeferido (HU-076 RN-008: o parecer
 * vai ao Regin nos dois casos) — ao integrador, via o contrato ReginParecerNotifier.
 *
 * ANTI-FACHADA (entrega-funcional): o binding atual é o UnavailableReginParecerNotifier,
 * que LANÇA ReginUnavailableException (a transmissão não ocorreu, contrato/homologação
 * pendente da Fase 13). O listener CAPTURA a exceção e AUDITA a pendência de integração
 * (logName 'integracoes', result 'bloqueado') — JAMAIS registra sucesso fictício nem
 * simula o envio. O caminho de sucesso é provado com um fake do notifier (prova de que a
 * lógica liga sozinha quando a Fase 13 trocar SÓ o binding).
 *
 * CRÍTICO (lição da Fase 8 — RN-002): o registro é único por auto-descoberta
 * (type-hint do evento no handle), NUNCA via Event::listen, que duplicaria a
 * auditoria. A não-duplicação é travada por CONTAGEM.
 */
class ComunicarResultadoReginListenerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Solicitação protocolada + sua decisão imutável — o payload do evento.
     *
     * @return array{0: ViabilityRequest, 1: ViabilityDecision}
     */
    private function requestComDecisao(bool $indeferida = false): array
    {
        $request = ViabilityRequest::factory()->protocoled()->create();

        $factory = ViabilityDecision::factory();
        $decision = ($indeferida ? $factory->indeferida() : $factory)
            ->create(['viability_request_id' => $request->id]);

        return [$request, $decision];
    }

    /**
     * Fake do notifier que REGISTRA cada chamada (prova de que a lógica liga
     * quando o contrato existir — Fase 13). Spy SÓ no teste, prática padrão.
     */
    private function fakeNotifierRegistrador(): ReginParecerNotifier
    {
        return new class implements ReginParecerNotifier
        {
            /** @var list<array{request: ViabilityRequest, decision: ViabilityDecision}> */
            public array $chamadas = [];

            public function notifyParecer(ViabilityRequest $request, ViabilityDecision $decision): void
            {
                $this->chamadas[] = ['request' => $request, 'decision' => $decision];
            }
        };
    }

    public function test_canal_bloqueado_audita_pendencia_uma_unica_vez_sem_quebrar_o_fluxo(): void
    {
        // Caminho REAL hoje: binding default (Unavailable) lança ReginUnavailableException.
        // O listener captura e audita a pendência — NUNCA sucesso fictício (anti-fachada).
        [$request, $decision] = $this->requestComDecisao();

        // Não relança para fora: a decisão (já gravada/auditada síncrona) não pode
        // falhar por causa da integração bloqueada. event() retorna normalmente.
        event(new ResultadoEmitido($request, $decision));

        $pendencias = Activity::query()
            ->where('log_name', 'integracoes')
            ->where('event', 'regin-parecer')
            ->where('result', 'bloqueado')
            ->get();

        // CONTAGEM (auto-descoberta, sem Event::listen): exatamente UMA pendência.
        $this->assertCount(1, $pendencias, 'O listener deve auditar a pendência Regin exatamente uma vez.');
        $this->assertSame($request->id, $pendencias->first()->properties['viability_request_id']);

        // Nenhum sucesso fictício: o canal está bloqueado.
        $this->assertSame(0, Activity::query()
            ->where('log_name', 'integracoes')
            ->where('event', 'regin-parecer')
            ->where('result', 'sucesso')
            ->count());
    }

    public function test_caminho_de_sucesso_comunica_o_parecer_e_audita_sucesso(): void
    {
        // Com o contrato DISPONÍVEL (fake), o listener chama notifyParecer e audita
        // 'sucesso' — prova de que a lógica liga sozinha quando a Fase 13 ligar o binding.
        [$request, $decision] = $this->requestComDecisao();

        $fake = $this->fakeNotifierRegistrador();
        $this->app->instance(ReginParecerNotifier::class, $fake);

        event(new ResultadoEmitido($request, $decision));

        $this->assertCount(1, $fake->chamadas, 'O listener deve chamar notifyParecer no contrato real.');
        $this->assertSame($request->id, $fake->chamadas[0]['request']->id);

        $sucessos = Activity::query()
            ->where('log_name', 'integracoes')
            ->where('event', 'regin-parecer')
            ->where('result', 'sucesso')
            ->get();

        $this->assertCount(1, $sucessos, 'O sucesso da comunicação deve ser auditado uma única vez.');
        $this->assertSame($request->id, $sucessos->first()->properties['viability_request_id']);

        // Sem pendência 'bloqueado' quando o canal está disponível.
        $this->assertSame(0, Activity::query()
            ->where('log_name', 'integracoes')
            ->where('event', 'regin-parecer')
            ->where('result', 'bloqueado')
            ->count());
    }

    public function test_parecer_indeferido_tambem_e_comunicado_ao_regin(): void
    {
        // HU-076 RN-008: o parecer vai ao Regin nos DOIS casos (deferido e indeferido).
        [$request, $decision] = $this->requestComDecisao(indeferida: true);

        $fake = $this->fakeNotifierRegistrador();
        $this->app->instance(ReginParecerNotifier::class, $fake);

        event(new ResultadoEmitido($request, $decision));

        $this->assertCount(1, $fake->chamadas, 'O indeferimento também é comunicado ao Regin (RN-008).');
        $this->assertSame(DecisionOutcome::Indeferida, $fake->chamadas[0]['decision']->outcome);
    }
}
