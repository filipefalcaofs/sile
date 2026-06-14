<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\TimelineSolicitacao;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consultar protocolo (HU-069) — consulta AUTENTICADA do dono com timeline e
 * status em linguagem simples (publicLabel), prazo estimado com ressalva honesta
 * (a medição real é HU-129/Fase 15) e auditoria RN-002. A timeline vem das
 * viability_request_transitions REAIS (anti-fachada — nada inventado).
 */
class ConsultarProtocoloTest extends TestCase
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

    private function timeline(): TimelineSolicitacao
    {
        return app(TimelineSolicitacao::class);
    }

    public function test_timeline_reflete_as_transicoes_reais_em_linguagem_simples(): void
    {
        // RN-004/006: a timeline vem das viability_request_transitions REAIS e
        // fala ao cidadão em linguagem simples (publicLabel). No modo público o
        // motivo interno e o rótulo técnico NÃO são expostos (LGPD).
        $user = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->draft()->withPrimaryCnae()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);

        // Transição REAL rascunho→protocolada — fonte da timeline.
        app(ViabilityRequestStateMachine::class)->transition(
            $solicitacao,
            ViabilityRequestStatus::Protocolada,
            actor: $user,
            reason: 'Protocolo efetuado',
            publicLabel: ViabilityRequestStatus::Protocolada->publicLabel(),
        );

        $timeline = $this->timeline()->build($solicitacao->refresh(), publico: false);

        $this->assertSame('protocolada', $timeline['status_atual']['value']);
        $this->assertSame(
            ViabilityRequestStatus::Protocolada->publicLabel(),
            $timeline['status_atual']['public_label'],
        );
        $this->assertSame(
            ViabilityRequestStatus::Protocolada->label(),
            $timeline['status_atual']['label'],
        );

        $this->assertCount(1, $timeline['etapas']);
        $this->assertSame(
            ViabilityRequestStatus::Protocolada->publicLabel(),
            $timeline['etapas'][0]['rotulo'],
        );
        $this->assertNotNull($timeline['etapas'][0]['data']);
        $this->assertSame('Protocolo efetuado', $timeline['etapas'][0]['motivo']);

        // Prazo estimado parametrizado COM ressalva honesta (RN-005).
        $this->assertSame(30, $timeline['prazo_estimado']['dias']);
        $this->assertStringContainsString('estimativa', mb_strtolower($timeline['prazo_estimado']['ressalva']));
        $this->assertStringContainsString('HU-129', $timeline['prazo_estimado']['ressalva']);

        // Modo público: SÓ data + rótulo amigável (sem motivo, sem rótulo técnico).
        $publica = $this->timeline()->build($solicitacao, publico: true);
        $this->assertArrayNotHasKey('motivo', $publica['etapas'][0]);
        $this->assertArrayNotHasKey('status_label', $publica['etapas'][0]);
        $this->assertArrayNotHasKey('label', $publica['status_atual']);
        $this->assertSame([], $publica['pendencias']);
    }

    public function test_timeline_lista_pendencia_do_requerente_no_rascunho(): void
    {
        // RN-006: em rascunho a pendência do requerente é concluir/protocolar —
        // derivada do status REAL, nunca inventada. Sem transições ainda.
        $user = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);

        $timeline = $this->timeline()->build($solicitacao, publico: false);

        $this->assertSame([], $timeline['etapas']);
        $this->assertNotEmpty($timeline['pendencias']);
    }
}
