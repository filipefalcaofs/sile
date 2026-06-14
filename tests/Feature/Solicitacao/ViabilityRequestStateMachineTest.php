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
}
