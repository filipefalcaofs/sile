<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Events\SolicitacaoProtocolada;
use App\Models\Cnae;
use App\Models\DocumentRequirement;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\DocumentacaoIncompletaException;
use App\Services\Solicitacao\InvalidStatusTransitionException;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use App\Services\Solicitacao\SolicitacaoIncompletaException;
use App\Services\Solicitacao\ViabilityRequestStateMachine;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

/**
 * Protocolar a solicitação (HU-068) — o coração da fase. Dentro de UMA
 * transação: valida os documentos obrigatórios (BLOQUEIA com aviso se faltar —
 * HU-067), gera o número único (ProtocolNumberGenerator com lock + unique),
 * transiciona rascunho→protocolada (ViabilityRequestStateMachine: timeline +
 * auditoria RN-002) e congela o snapshot da simulação (RN-003). A simulação NÃO
 * bloqueia: com tendência de indeferimento e ciência do requerente, registra
 * applicant_proceeded_despite (HU-141 RN-002, direito de petição). Após o commit
 * dispara SolicitacaoProtocolada — o PRIMEIRO evento de domínio do sistema; a
 * auditoria do protocolo NÃO depende do evento (a transição síncrona garante a
 * trilha mesmo se um listener falhar).
 */
class ProtocolarSolicitacaoTest extends TestCase
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

    /**
     * Rascunho COMPLETO (empresa, imóvel/polígono, área e CNAE principal) cujo
     * requerente é o usuário — pronto para protocolar.
     */
    private function completeDraft(User $user): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'used_area_m2' => 120.0,
        ]);

        $cnae = Cnae::factory()->create();
        $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => true]);

        return $solicitacao;
    }

    private function service(): ProtocolarSolicitacaoService
    {
        return app(ProtocolarSolicitacaoService::class);
    }

    public function test_protocola_gera_numero_unico(): void
    {
        // CA-01: rascunho completo → status protocolada, número no formato
        // VIA-AAAA-NNNNNN, protocoled_at setado, 1 transição rascunho→protocolada
        // e auditoria 'transicao' (RN-002).
        $user = $this->portalUser();
        $solicitacao = $this->completeDraft($user);

        $resultado = $this->service()->protocol($solicitacao, $user);

        $this->assertSame(ViabilityRequestStatus::Protocolada, $resultado->status);
        $this->assertMatchesRegularExpression('/^VIA-\d{4}-\d{6}$/', (string) $resultado->protocol_number);
        $this->assertNotNull($resultado->protocoled_at);

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Protocolada, $solicitacao->status);
        $this->assertNotNull($solicitacao->protocol_number);

        $this->assertSame(1, $solicitacao->transitions()->count());
        $transition = $solicitacao->transitions()->first();
        $this->assertSame(ViabilityRequestStatus::Rascunho, $transition->from_status);
        $this->assertSame(ViabilityRequestStatus::Protocolada, $transition->to_status);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'solicitacoes',
            'event' => 'transicao',
            'subject_type' => $solicitacao->getMorphClass(),
            'subject_id' => $solicitacao->id,
        ]);
    }

    public function test_bloqueia_sem_documento_obrigatorio(): void
    {
        // HU-067: faltando um documento obrigatório (requisito vinculado ao CNAE,
        // sem anexo) → DocumentacaoIncompletaException com a lista (aviso, nunca
        // silencioso); status continua rascunho e NENHUM número é consumido.
        $user = $this->portalUser();
        $solicitacao = $this->completeDraft($user);

        $cnae = $solicitacao->cnaes()->first();
        $requisito = DocumentRequirement::factory()->required()->create();
        $requisito->cnaes()->attach($cnae->id);

        try {
            $this->service()->protocol($solicitacao, $user);
            $this->fail('Esperava DocumentacaoIncompletaException por documento obrigatório faltante.');
        } catch (DocumentacaoIncompletaException $e) {
            $this->assertStringContainsString($requisito->name, $e->getMessage());
            $this->assertContains($requisito->name, $e->requisitos);
        }

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Rascunho, $solicitacao->status);
        $this->assertNull($solicitacao->protocol_number);
        $this->assertSame(0, $solicitacao->transitions()->count());
    }

    public function test_bloqueia_sem_dados_minimos(): void
    {
        // FA-01 (dados incompletos): sem CNAE principal o protocolo é bloqueado
        // (SolicitacaoIncompletaException) ANTES de consumir número — anti-fachada.
        $user = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'used_area_m2' => 120.0,
        ]);

        try {
            $this->service()->protocol($solicitacao, $user);
            $this->fail('Esperava SolicitacaoIncompletaException por dados mínimos ausentes.');
        } catch (SolicitacaoIncompletaException $e) {
            $this->assertNotEmpty($e->campos);
        }

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Rascunho, $solicitacao->status);
        $this->assertNull($solicitacao->protocol_number);
    }

    public function test_simulacao_nao_bloqueia_e_registra_ciencia(): void
    {
        // HU-141 RN-002: tendência de indeferimento + ciência (proceedDespite) →
        // protocola mesmo assim e registra applicant_proceeded_despite; o snapshot
        // persistido é CONGELADO (RN-003), nunca reprocessado.
        $user = $this->portalUser();
        $solicitacao = $this->completeDraft($user);

        $snapshot = [
            'ponto' => ['lat' => -12.9710, 'lng' => -38.5107],
            'area_m2' => 120.0,
            'por_cnae' => [['cnae' => '4712100', 'tendencia' => 'nao_permitido']],
        ];
        $solicitacao->forceFill([
            'simulation_snapshot' => $snapshot,
            'simulation_resultado' => 'nao_permitido',
            'simulated_at' => now(),
        ])->save();

        $resultado = $this->service()->protocol($solicitacao, $user, proceedDespite: true);

        $this->assertSame(ViabilityRequestStatus::Protocolada, $resultado->status);
        $this->assertTrue((bool) $resultado->applicant_proceeded_despite);

        $solicitacao->refresh();
        $this->assertTrue((bool) $solicitacao->applicant_proceeded_despite);
        // Snapshot CONGELADO: tendência e resultado preservados (não reprocessa).
        $this->assertSame('nao_permitido', $solicitacao->simulation_resultado);
        $this->assertSame('nao_permitido', $solicitacao->simulation_snapshot['por_cnae'][0]['tendencia']);
        $this->assertNotNull($solicitacao->simulated_at);
    }

    public function test_ciencia_nao_registrada_quando_simulacao_favoravel(): void
    {
        // A ciência (applicant_proceeded_despite) só é registrada quando a
        // tendência é desfavorável (indeferimento). Sem simulação desfavorável,
        // mesmo com proceedDespite=true, não há ciência a registrar.
        $user = $this->portalUser();
        $solicitacao = $this->completeDraft($user);

        $this->service()->protocol($solicitacao, $user, proceedDespite: true);

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Protocolada, $solicitacao->status);
        $this->assertFalse((bool) $solicitacao->applicant_proceeded_despite);
    }

    public function test_dispara_evento_apos_commit(): void
    {
        // O primeiro evento de domínio é disparado APÓS o protocolo bem-sucedido,
        // carregando a solicitação já com o número (commit efetivado).
        Event::fake([SolicitacaoProtocolada::class]);

        $user = $this->portalUser();
        $solicitacao = $this->completeDraft($user);

        $this->service()->protocol($solicitacao, $user);

        Event::assertDispatched(
            SolicitacaoProtocolada::class,
            fn (SolicitacaoProtocolada $event) => $event->request->is($solicitacao)
                && $event->request->protocol_number !== null,
        );
    }

    public function test_nao_dispara_evento_no_rollback(): void
    {
        // Se a transação falhar (rollback), NADA é efetivado: sem número, status
        // intacto e o evento NÃO é disparado (a linha de dispatch fica após o
        // commit, nunca alcançada no erro).
        Event::fake([SolicitacaoProtocolada::class]);

        $user = $this->portalUser();
        $solicitacao = $this->completeDraft($user);

        // Falha forçada DENTRO da transação (após gerar o número): a máquina de
        // estados lança → DB::transaction faz rollback.
        $this->mock(ViabilityRequestStateMachine::class, function ($mock) {
            $mock->shouldReceive('transition')->andThrow(new RuntimeException('falha simulada na transição'));
        });

        try {
            $this->service()->protocol($solicitacao, $user);
            $this->fail('Esperava a exceção da transição.');
        } catch (RuntimeException $e) {
            $this->assertSame('falha simulada na transição', $e->getMessage());
        }

        Event::assertNotDispatched(SolicitacaoProtocolada::class);

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Rascunho, $solicitacao->status);
        $this->assertNull($solicitacao->protocol_number);
    }

    public function test_nao_protocola_fora_de_rascunho(): void
    {
        // Já protocolada → bloqueado (status != rascunho): a pré-condição lança
        // InvalidStatusTransitionException e NÃO consome novo número.
        $user = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->protocoled()->withPrimaryCnae()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);
        $numeroAntes = $solicitacao->protocol_number;

        $this->expectException(InvalidStatusTransitionException::class);

        try {
            $this->service()->protocol($solicitacao, $user);
        } finally {
            $solicitacao->refresh();
            $this->assertSame($numeroAntes, $solicitacao->protocol_number);
            $this->assertSame(ViabilityRequestStatus::Protocolada, $solicitacao->status);
        }
    }

    public function test_auditoria_independe_do_evento(): void
    {
        // RN-002: mesmo com o evento fakeado (listener real NÃO roda), a auditoria
        // da transição (síncrona, na transação) é gravada — a trilha não depende
        // do evento de domínio.
        Event::fake([SolicitacaoProtocolada::class]);

        $user = $this->portalUser();
        $solicitacao = $this->completeDraft($user);

        $this->service()->protocol($solicitacao, $user);

        // O evento foi disparado, mas o listener real não rodou (fake).
        Event::assertDispatched(SolicitacaoProtocolada::class);

        // A auditoria 'transicao' e o marco amigável (public_label) vêm da máquina
        // de estados síncrona, não do listener.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'solicitacoes',
            'event' => 'transicao',
            'subject_id' => $solicitacao->id,
        ]);
        $this->assertNotNull(
            $solicitacao->transitions()->whereNotNull('public_label')->first()
        );
    }
}
