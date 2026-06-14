<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\CancelamentoNaoPermitidoException;
use App\Services\Solicitacao\CancelarSolicitacaoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cancelar a solicitação (HU-070): o dono interrompe um processo indevido ou
 * duplicado enquanto NÃO decidido — de rascunho e de protocolada (antes da
 * decisão) — via ViabilityRequestStateMachine (transição auditada + marco na
 * timeline). Os estados canceláveis são PARAMETRIZÁVEIS
 * (solicitacao.cancelamento.estados_cancelaveis); a definição fina é pendência
 * SEDUR. Cancelar grava cancelled_at/cancelled_reason/cancelled_by_user_id de
 * verdade (anti-fachada); fora dos estados canceláveis bloqueia com aviso e
 * audita (RN-002); só o dono cancela (policy, CA-04).
 */
class CancelarSolicitacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Cidadão habilitado a navegar no portal (papel + termo LGPD aceito).
     */
    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    private function service(): CancelarSolicitacaoService
    {
        return app(CancelarSolicitacaoService::class);
    }

    public function test_cancela_rascunho(): void
    {
        // CA-01: o dono cancela um rascunho → status cancelada, cancelled_*
        // preenchidos, marco na timeline (public_label + motivo) e auditoria
        // 'transicao' com o usuário (RN-002).
        $owner = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->draft()->withPrimaryCnae()->create([
            'requester_user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->from(route('portal.solicitacoes.index'))
            ->delete(route('portal.solicitacoes.cancelar', $solicitacao), [
                'reason' => 'Cadastro duplicado por engano.',
            ])
            ->assertRedirect(route('portal.solicitacoes.index'))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Cancelada, $solicitacao->status);
        $this->assertNotNull($solicitacao->cancelled_at);
        $this->assertSame('Cadastro duplicado por engano.', $solicitacao->cancelled_reason);
        $this->assertSame($owner->id, $solicitacao->cancelled_by_user_id);

        $transition = $solicitacao->transitions()->first();
        $this->assertNotNull($transition);
        $this->assertSame(ViabilityRequestStatus::Rascunho, $transition->from_status);
        $this->assertSame(ViabilityRequestStatus::Cancelada, $transition->to_status);
        $this->assertSame('Cancelada a pedido do requerente', $transition->public_label);
        $this->assertSame('Cadastro duplicado por engano.', $transition->reason);

        // RN-002: a auditoria da transição registra o usuário e a solicitação.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'solicitacoes',
            'event' => 'transicao',
            'subject_type' => $solicitacao->getMorphClass(),
            'subject_id' => $solicitacao->id,
            'causer_id' => $owner->id,
        ]);
    }

    public function test_cancela_protocolada_antes_da_decisao(): void
    {
        // Dentro dos estados canceláveis (default): protocolada → cancelada. O
        // número de protocolo permanece (histórico preservado).
        $owner = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->protocoled()->withPrimaryCnae()->create([
            'requester_user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
        ]);
        $numeroProtocolo = $solicitacao->protocol_number;

        $this->actingAs($owner)
            ->from(route('portal.solicitacoes.index'))
            ->delete(route('portal.solicitacoes.cancelar', $solicitacao), [
                'reason' => 'Não preciso mais da viabilidade.',
            ])
            ->assertRedirect(route('portal.solicitacoes.index'))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Cancelada, $solicitacao->status);
        $this->assertNotNull($solicitacao->cancelled_at);
        $this->assertSame($numeroProtocolo, $solicitacao->protocol_number);

        $transition = $solicitacao->transitions()->first();
        $this->assertSame(ViabilityRequestStatus::Protocolada, $transition->from_status);
        $this->assertSame(ViabilityRequestStatus::Cancelada, $transition->to_status);
    }

    public function test_bloqueia_fora_dos_estados_cancelaveis(): void
    {
        // CA-03: com o parâmetro restrito a ['rascunho'], cancelar uma
        // protocolada é BLOQUEADO (CancelamentoNaoPermitidoException) e auditado
        // — o estado e a timeline permanecem intactos (nada é cancelado).
        config(['sile.solicitacao.cancelamento.estados_cancelaveis' => ['rascunho']]);

        $owner = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->protocoled()->withPrimaryCnae()->create([
            'requester_user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
        ]);

        try {
            $this->service()->cancel($solicitacao, $owner, 'Tentativa fora dos estados canceláveis.');
            $this->fail('Esperava CancelamentoNaoPermitidoException ao cancelar fora dos estados canceláveis.');
        } catch (CancelamentoNaoPermitidoException $e) {
            $this->assertSame(ViabilityRequestStatus::Protocolada, $e->status);
        }

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Protocolada, $solicitacao->status);
        $this->assertNull($solicitacao->cancelled_at);
        $this->assertNull($solicitacao->cancelled_by_user_id);
        $this->assertSame(0, $solicitacao->transitions()->count());

        // RN-002: o bloqueio é auditado com resultado 'bloqueado' e a solicitação
        // como subject.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'solicitacoes',
            'event' => 'cancelamento-bloqueado',
            'subject_type' => $solicitacao->getMorphClass(),
            'subject_id' => $solicitacao->id,
            'result' => 'bloqueado',
        ]);
    }

    public function test_controller_traduz_bloqueio_em_aviso(): void
    {
        // O bloqueio de estado vira flash.error (aviso, nunca silencioso) e nada
        // é cancelado; a tentativa é auditada com o usuário (RN-002).
        config(['sile.solicitacao.cancelamento.estados_cancelaveis' => ['rascunho']]);

        $owner = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->protocoled()->withPrimaryCnae()->create([
            'requester_user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->from(route('portal.solicitacoes.index'))
            ->delete(route('portal.solicitacoes.cancelar', $solicitacao), [
                'reason' => 'Tentativa fora dos estados canceláveis.',
            ])
            ->assertRedirect(route('portal.solicitacoes.index'))
            ->assertSessionHas('error');

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Protocolada, $solicitacao->status);
        $this->assertNull($solicitacao->cancelled_at);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'solicitacoes',
            'event' => 'cancelamento-bloqueado',
            'subject_id' => $solicitacao->id,
            'result' => 'bloqueado',
            'causer_id' => $owner->id,
        ]);
    }

    public function test_so_dono_cancela(): void
    {
        // CA-04: terceiro não cancela (403, auditado globalmente) e a solicitação
        // permanece intacta.
        $owner = $this->portalUser();
        $stranger = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->draft()->withPrimaryCnae()->create([
            'requester_user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
        ]);

        $this->actingAs($stranger)
            ->delete(route('portal.solicitacoes.cancelar', $solicitacao), [
                'reason' => 'Tentando cancelar processo de terceiro.',
            ])
            ->assertForbidden();

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Rascunho, $solicitacao->status);
        $this->assertNull($solicitacao->cancelled_at);
        $this->assertSame(0, $solicitacao->transitions()->count());
    }

    public function test_motivo_obrigatorio(): void
    {
        // O motivo do cancelamento é obrigatório (registro do porquê na trilha):
        // sem reason → erro de validação, nada é cancelado.
        $owner = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->draft()->withPrimaryCnae()->create([
            'requester_user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->from(route('portal.solicitacoes.index'))
            ->delete(route('portal.solicitacoes.cancelar', $solicitacao), [])
            ->assertRedirect(route('portal.solicitacoes.index'))
            ->assertSessionHasErrors('reason');

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Rascunho, $solicitacao->status);
        $this->assertNull($solicitacao->cancelled_at);
    }
}
