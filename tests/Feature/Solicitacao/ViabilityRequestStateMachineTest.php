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

    /**
     * EP10 ADICIONA as saídas da análise humana a partir de em_analise (sem
     * tocar a Fase 8/9): o analista abre pendência (HU-083/084) ou decide
     * (HU-086/087). Cada transição grava timeline + auditoria (RN-002).
     */
    public function test_em_analise_transiciona_para_pendencia_e_decisao(): void
    {
        $destinos = [
            ViabilityRequestStatus::EmPendencia,
            ViabilityRequestStatus::Deferida,
            ViabilityRequestStatus::Indeferida,
        ];

        foreach ($destinos as $i => $destino) {
            $request = ViabilityRequest::factory()->protocoled()->create([
                'protocol_number' => 'VIA-2026-'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
            ]);
            $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

            $this->assertTrue(
                $this->machine()->canTransition(ViabilityRequestStatus::EmAnalise, $destino),
                "em_analise→{$destino->value} deveria ser válida no EP10.",
            );

            $this->machine()->transition($request, $destino);
            $this->assertSame($destino, $request->fresh()->status);
            $this->assertSame(1, $request->transitions()->count());
        }
    }

    public function test_em_analise_para_em_pendencia_audita_transicao(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        $this->machine()->transition($request, ViabilityRequestStatus::EmPendencia);

        $activity = Activity::query()
            ->where('log_name', 'solicitacoes')
            ->where('event', 'transicao')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame('em_analise', $activity->properties['from']);
        $this->assertSame('em_pendencia', $activity->properties['to']);
    }

    /**
     * Ciclo de pendência (HU-083/084): respondida/saneada a pendência, o
     * processo VOLTA para a análise — só de em_analise se pode decidir.
     */
    public function test_em_pendencia_volta_para_em_analise(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['status' => ViabilityRequestStatus::EmPendencia])->save();

        $this->assertTrue(
            $this->machine()->canTransition(ViabilityRequestStatus::EmPendencia, ViabilityRequestStatus::EmAnalise),
        );

        $this->machine()->transition($request, ViabilityRequestStatus::EmAnalise);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertSame(1, $request->transitions()->count());
    }

    /**
     * Anti-regressão da análise humana: a decisão é FINAL (HU-089 encerramento).
     * A pendência (convite) volta a em_analise na resposta OU é INDEFERIDA na
     * expiração do prazo (Fase 2a — relatório SEDUR 2026-07-09); porém deferir
     * a partir de em_pendencia segue inválido (defere só a partir de em_analise).
     */
    public function test_decisao_final_e_ciclo_de_pendencia_travados(): void
    {
        $this->assertFalse(
            $this->machine()->canTransition(ViabilityRequestStatus::Deferida, ViabilityRequestStatus::EmAnalise),
            'deferida→em_analise deve ser inválida (decisão é final).',
        );
        $this->assertFalse(
            $this->machine()->canTransition(ViabilityRequestStatus::Indeferida, ViabilityRequestStatus::EmAnalise),
            'indeferida→em_analise deve ser inválida (decisão é final).',
        );
        $this->assertFalse(
            $this->machine()->canTransition(ViabilityRequestStatus::EmPendencia, ViabilityRequestStatus::Deferida),
            'em_pendencia→deferida deve ser inválida (decide só a partir de em_analise).',
        );
        $this->assertTrue(
            $this->machine()->canTransition(ViabilityRequestStatus::EmPendencia, ViabilityRequestStatus::Indeferida),
            'em_pendencia→indeferida deve ser VÁLIDA (expiração do convite indefere — Fase 2a).',
        );
        $this->assertFalse(
            $this->machine()->canTransition(ViabilityRequestStatus::EmAnalise, ViabilityRequestStatus::Protocolada),
            'em_analise→protocolada deve ser inválida (não regride ao protocolo).',
        );
    }

    public function test_decisao_final_lanca_excecao_e_nao_altera_estado(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['status' => ViabilityRequestStatus::Deferida])->save();

        try {
            $this->machine()->transition($request, ViabilityRequestStatus::EmAnalise);
            $this->fail('Esperava InvalidStatusTransitionException para deferida→em_analise (decisão é final).');
        } catch (InvalidStatusTransitionException) {
            // Encerramento HU-089: estado inalterado e nenhuma transição gravada.
        }

        $this->assertSame(ViabilityRequestStatus::Deferida, $request->fresh()->status);
        $this->assertSame(0, $request->transitions()->count());
    }
}
