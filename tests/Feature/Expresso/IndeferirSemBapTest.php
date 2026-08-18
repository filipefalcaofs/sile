<?php

namespace Tests\Feature\Expresso;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\Activity;
use App\Models\ViabilityRequest;
use App\Services\Expresso\BusinessDeadlineCalculator;
use App\Services\Expresso\IndeferirSemBapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Indeferimento por prazo BAP (HU-134) — o motor REAL da rotina dormente: uma
 * solicitação parada em aguardando_bap além do prazo é indeferida de verdade
 * (decisão imutável + transição + auditoria + evento), reusando o caminho de
 * emissão do fluxo expresso. NÃO vai à SEFAZ (RN-003) — só comunica ao Regin
 * via o ResultadoEmitido (garantido pelos listeners da Wave 4).
 *
 * Prova que a lógica funciona AGORA (com seed de aguardando_bap), embora em
 * produção nada entre em aguardando_bap até o Regin alimentar bap_due_at
 * (Fase 13) — sem fachada: o seam BusinessDeadlineCalculator e a guarda de
 * estado mantêm a regra honesta e parametrizável.
 */
class IndeferirSemBapTest extends TestCase
{
    use RefreshDatabase;

    public function test_indefere_aguardando_bap_vencida_com_motivo_transicao_e_evento(): void
    {
        // Isola o serviço: faka só o ResultadoEmitido (a auditoria/decisão é
        // síncrona na transação e NÃO depende do evento — lição Fase 8).
        Event::fake([ResultadoEmitido::class]);

        $request = ViabilityRequest::factory()->awaitingBap()->create();

        $decision = app(IndeferirSemBapService::class)->indeferir($request);

        $this->assertSame(DecisionOutcome::Indeferida, $decision->outcome);
        $this->assertSame('indeferido sem atuação', $decision->reason);
        $this->assertSame('expresso', $decision->flow);
        $this->assertNull($decision->tvl_product_number);
        $this->assertNull($decision->decided_by_user_id);

        // Transição aguardando_bap → indeferida (timeline registrada).
        $this->assertSame(ViabilityRequestStatus::Indeferida, $request->fresh()->status);
        $this->assertDatabaseHas('viability_request_transitions', [
            'viability_request_id' => $request->id,
            'from_status' => ViabilityRequestStatus::AguardandoBap->value,
            'to_status' => ViabilityRequestStatus::Indeferida->value,
        ]);

        // Auditoria síncrona da decisão por prazo BAP (RN-002).
        $this->assertSame(1, Activity::query()
            ->where('log_name', 'expresso')
            ->where('event', 'decisao')
            ->where('result', 'indeferida')
            ->count());

        Event::assertDispatched(
            ResultadoEmitido::class,
            fn (ResultadoEmitido $event): bool => $event->decision->is($decision),
        );
    }

    public function test_calculadora_de_prazo_usa_horas_calendario_e_detecta_vencimento(): void
    {
        $calculator = new BusinessDeadlineCalculator;
        $from = Carbon::parse('2026-06-14 10:00:00');

        // Seam HU-137: hoje horas-calendário (addHours); dias úteis depois.
        $this->assertSame(
            $from->copy()->addHours(48)->toIso8601String(),
            $calculator->dueAt($from, 48)->toIso8601String(),
        );

        $this->assertTrue($calculator->isOverdue(Carbon::now()->subHour()));
        $this->assertFalse($calculator->isOverdue(Carbon::now()->addHour()));
    }

    public function test_guarda_recusa_solicitacao_que_nao_esta_aguardando_bap(): void
    {
        // Anti-fachada: o serviço só indefere por BAP; uma protocolada (que o
        // motor normal decide) não pode ser indeferida "sem atuação".
        $request = ViabilityRequest::factory()->protocoled()->create();

        $lancou = false;

        try {
            app(IndeferirSemBapService::class)->indeferir($request);
        } catch (InvalidArgumentException) {
            $lancou = true;
        }

        $this->assertTrue($lancou, 'Esperava InvalidArgumentException para solicitação fora de aguardando_bap.');
        $this->assertDatabaseCount('viability_decisions', 0);
        $this->assertSame(ViabilityRequestStatus::Protocolada, $request->fresh()->status);
    }
}
