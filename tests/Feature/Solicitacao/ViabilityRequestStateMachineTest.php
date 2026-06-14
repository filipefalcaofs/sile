<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\Activity;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\InvalidStatusTransitionException;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use App\Support\Audit\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Máquina de estados da solicitação (HU-068/070): mapa explícito SÓ das
 * transições ativas (rascunho→protocolada/cancelada; protocolada→cancelada).
 * Transição inválida lança exceção (CA-03) sem mudar o estado nem gravar
 * timeline; toda transição válida grava viability_request_transitions e audita
 * (RN-002). Os ganchos das Fases 9/10/11 NÃO são transicionáveis aqui.
 */
class ViabilityRequestStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private function machine(): ViabilityRequestStateMachine
    {
        return new ViabilityRequestStateMachine(app(AuditService::class));
    }

    public function test_transicao_valida_rascunho_para_protocolada(): void
    {
        $request = ViabilityRequest::factory()->draft()->create();

        $transition = $this->machine()->transition($request, ViabilityRequestStatus::Protocolada);

        $this->assertSame(ViabilityRequestStatus::Protocolada, $request->fresh()->status);
        $this->assertSame(1, $request->transitions()->count());
        $this->assertSame(ViabilityRequestStatus::Rascunho, $transition->from_status);
        $this->assertSame(ViabilityRequestStatus::Protocolada, $transition->to_status);

        $activity = Activity::query()
            ->where('log_name', 'solicitacoes')
            ->where('event', 'transicao')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame($request->id, $activity->properties['viability_request_id']);
        $this->assertSame('rascunho', $activity->properties['from']);
        $this->assertSame('protocolada', $activity->properties['to']);
    }

    public function test_transicao_valida_para_cancelada_de_rascunho_e_protocolada(): void
    {
        $rascunho = ViabilityRequest::factory()->draft()->create();
        $this->machine()->transition($rascunho, ViabilityRequestStatus::Cancelada);
        $this->assertSame(ViabilityRequestStatus::Cancelada, $rascunho->fresh()->status);

        $protocolada = ViabilityRequest::factory()->protocoled()->create();
        $this->machine()->transition($protocolada, ViabilityRequestStatus::Cancelada);
        $this->assertSame(ViabilityRequestStatus::Cancelada, $protocolada->fresh()->status);
    }

    public function test_transicao_invalida_lanca_excecao_e_nao_altera_estado(): void
    {
        $protocolada = ViabilityRequest::factory()->protocoled()->create();

        try {
            $this->machine()->transition($protocolada, ViabilityRequestStatus::Rascunho);
            $this->fail('Esperava InvalidStatusTransitionException para protocolada→rascunho.');
        } catch (InvalidStatusTransitionException) {
            // CA-03: estado inalterado e nenhuma transição gravada.
        }

        $this->assertSame(ViabilityRequestStatus::Protocolada, $protocolada->fresh()->status);
        $this->assertSame(0, $protocolada->transitions()->count());
    }

    public function test_ganchos_nao_sao_transicionaveis(): void
    {
        $request = ViabilityRequest::factory()->draft()->create();

        // Gancho das Fases 9/10/11 NÃO está no mapa desta fase.
        $this->assertFalse(
            $this->machine()->canTransition(ViabilityRequestStatus::Rascunho, ViabilityRequestStatus::EmAnalise),
        );

        try {
            $this->machine()->transition($request, ViabilityRequestStatus::EmAnalise);
            $this->fail('Gancho em_analise não deveria ser transicionável nesta fase.');
        } catch (InvalidStatusTransitionException) {
            // esperado
        }

        $this->assertSame(ViabilityRequestStatus::Rascunho, $request->fresh()->status);
        $this->assertSame(0, $request->transitions()->count());
    }

    public function test_transicao_registra_ator_e_label_publico(): void
    {
        $request = ViabilityRequest::factory()->draft()->create();
        $actor = User::factory()->create();

        $transition = $this->machine()->transition(
            $request,
            ViabilityRequestStatus::Protocolada,
            actor: $actor,
            reason: 'Protocolo efetuado pelo requerente.',
            publicLabel: 'Recebida — em processamento',
        );

        $this->assertSame($actor->id, $transition->actor_user_id);
        $this->assertSame('Recebida — em processamento', $transition->public_label);
        $this->assertSame('Protocolo efetuado pelo requerente.', $transition->reason);
    }

    /**
     * EP09 ADICIONA as saídas de decisão de protocolada (sem tocar o protocolo
     * da Fase 8): o fluxo expresso defere/indefere automático, roteia para
     * análise (semi-expresso) ou aguarda a Junta (BAP).
     */
    public function test_protocolada_transiciona_para_estados_de_decisao(): void
    {
        $destinos = [
            ViabilityRequestStatus::EmAnalise,
            ViabilityRequestStatus::Deferida,
            ViabilityRequestStatus::Indeferida,
            ViabilityRequestStatus::AguardandoBap,
        ];

        foreach ($destinos as $i => $destino) {
            $request = ViabilityRequest::factory()->protocoled()->create([
                'protocol_number' => 'VIA-2026-'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
            ]);

            $this->assertTrue(
                $this->machine()->canTransition(ViabilityRequestStatus::Protocolada, $destino),
                "protocolada→{$destino->value} deveria ser válida no EP09.",
            );

            $this->machine()->transition($request, $destino);
            $this->assertSame($destino, $request->fresh()->status);
            $this->assertSame(1, $request->transitions()->count());
        }
    }

    public function test_protocolada_para_deferida_audita_transicao(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();

        $this->machine()->transition($request, ViabilityRequestStatus::Deferida);

        $activity = Activity::query()
            ->where('log_name', 'solicitacoes')
            ->where('event', 'transicao')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame('protocolada', $activity->properties['from']);
        $this->assertSame('deferida', $activity->properties['to']);
    }

    /**
     * aguardando_bap (HU-134) é a antessala da decisão final pós-Junta: pode
     * deferir, indeferir (prazo BAP vencido) ou cair em análise.
     */
    public function test_aguardando_bap_transiciona_para_decisao_e_analise(): void
    {
        $destinos = [
            ViabilityRequestStatus::Deferida,
            ViabilityRequestStatus::Indeferida,
            ViabilityRequestStatus::EmAnalise,
        ];

        foreach ($destinos as $i => $destino) {
            $request = ViabilityRequest::factory()->protocoled()->create([
                'protocol_number' => 'VIA-2026-'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
            ]);
            $request->forceFill(['status' => ViabilityRequestStatus::AguardandoBap])->save();

            $this->assertTrue(
                $this->machine()->canTransition(ViabilityRequestStatus::AguardandoBap, $destino),
                "aguardando_bap→{$destino->value} deveria ser válida no EP09.",
            );

            $this->machine()->transition($request, $destino);
            $this->assertSame($destino, $request->fresh()->status);
        }
    }

    public function test_aguardando_bap_nao_volta_para_protocolada(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['status' => ViabilityRequestStatus::AguardandoBap])->save();

        $this->assertFalse(
            $this->machine()->canTransition(ViabilityRequestStatus::AguardandoBap, ViabilityRequestStatus::Protocolada),
        );

        try {
            $this->machine()->transition($request, ViabilityRequestStatus::Protocolada);
            $this->fail('aguardando_bap→protocolada não deveria ser transicionável.');
        } catch (InvalidStatusTransitionException) {
            // esperado
        }

        $this->assertSame(ViabilityRequestStatus::AguardandoBap, $request->fresh()->status);
    }

    /**
     * Anti-regressão: o EP09 só ADICIONA entradas no mapa. Transições que a
     * Fase 8 nunca permitiu seguem bloqueadas (estado final cancelada não
     * "revive" como deferida; protocolada não regride a rascunho).
     */
    public function test_transicoes_invalidas_antigas_continuam_bloqueadas(): void
    {
        $this->assertFalse(
            $this->machine()->canTransition(ViabilityRequestStatus::Cancelada, ViabilityRequestStatus::Deferida),
            'cancelada→deferida deve permanecer inválida.',
        );
        $this->assertFalse(
            $this->machine()->canTransition(ViabilityRequestStatus::Protocolada, ViabilityRequestStatus::Rascunho),
            'protocolada→rascunho deve permanecer inválida.',
        );
        $this->assertFalse(
            $this->machine()->canTransition(ViabilityRequestStatus::Rascunho, ViabilityRequestStatus::EmAnalise),
            'rascunho→em_analise deve permanecer inválida.',
        );
        $this->assertFalse(
            $this->machine()->canTransition(ViabilityRequestStatus::Deferida, ViabilityRequestStatus::Indeferida),
            'deferida→indeferida deve permanecer inválida (decisão é final).',
        );
    }
}
