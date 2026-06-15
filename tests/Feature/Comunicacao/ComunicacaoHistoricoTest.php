<?php

namespace Tests\Feature\Comunicacao;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Enums\CommunicationType;
use App\Enums\ViabilityRequestStatus;
use App\Models\Communication;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Histórico unificado de comunicações por processo (HU-096): a fonte de verdade
 * ÚNICA é o ledger `communications` (NÃO junta EmailLog/notifications de forma
 * frágil) — todos os canais (email/in_app/whatsapp) e tipos (pendência aberta/
 * respondida/expirada, prazo vencendo, escalonamento SLA, resultado), com
 * status HONESTO, ordenados por data.
 *
 * Escopo: no portal só o dono/representado do processo (policy view); na gestão
 * gated por consultar-solicitacoes (REUSO — sem permissão nova). A consulta é
 * AUDITADA (RN-002). LGPD: o portal NÃO expõe o error_message interno do canal
 * (diagnóstico só na retaguarda).
 */
class ComunicacaoHistoricoTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Processo protocolado com protocolo único (evita colisão do protocoled()
     * hardcoded quando há mais de um processo no teste).
     */
    private function processo(?User $owner = null): ViabilityRequest
    {
        // Sem owner explícito, a factory cria o próprio requerente (FK NOT NULL).
        $attrs = $owner === null ? [] : [
            'requester_user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
        ];

        $request = ViabilityRequest::factory()->create($attrs);

        $request->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT),
            'protocoled_at' => now(),
        ])->save();

        return $request->refresh();
    }

    /**
     * Linha do ledger para o processo, com created_at controlado (a ordenação é
     * por data — created_at desc, id desc como desempate).
     */
    private function comunicacao(
        ViabilityRequest $processo,
        CommunicationType $type,
        CommunicationChannel $channel,
        CommunicationStatus $status = CommunicationStatus::NaFila,
        ?string $erro = null,
        ?Carbon $em = null,
    ): Communication {
        $comunicacao = Communication::factory()->create([
            'viability_request_id' => $processo->id,
            'type' => $type,
            'channel' => $channel,
            'status' => $status,
            'error_message' => $erro,
        ]);

        $comunicacao->forceFill(['created_at' => $em ?? now()])->save();

        return $comunicacao;
    }

    public function test_gestao_lista_historico_unificado_por_processo_ordenado_e_audita(): void
    {
        $processo = $this->processo();

        // Tipos e canais variados (Waves 2-3/07 gravam assim): o histórico junta
        // tudo do processo em uma linha do tempo única.
        $maisAntiga = $this->comunicacao($processo, CommunicationType::PendenciaAberta, CommunicationChannel::Email, CommunicationStatus::Enviado, em: now()->subDays(3));
        $this->comunicacao($processo, CommunicationType::PendenciaAberta, CommunicationChannel::InApp, CommunicationStatus::Enviado, em: now()->subDays(3));
        $maisRecente = $this->comunicacao($processo, CommunicationType::Resultado, CommunicationChannel::Email, CommunicationStatus::Enviado, em: now()->subDay());

        // Ruído: comunicação de OUTRO processo não pode vazar para este histórico.
        $this->comunicacao($this->processo(), CommunicationType::EscalonamentoSla, CommunicationChannel::InApp);

        $page = $this->actingAs($this->analista(), 'gestao')
            ->get(route('gestao.processos.comunicacoes', $processo))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('gestao/processos/comunicacoes', $page['component']);

        $comunicacoes = $page['props']['comunicacoes'];
        $this->assertCount(3, $comunicacoes);
        // Ordenado por data (mais recente primeiro).
        $this->assertSame($maisRecente->id, $comunicacoes[0]['id']);
        $this->assertSame($maisAntiga->id, $comunicacoes[2]['id']);
        // Unificado: canais e tipos distintos convivem na mesma listagem.
        $canais = array_column($comunicacoes, 'channel');
        $this->assertContains(CommunicationChannel::Email->value, $canais);
        $this->assertContains(CommunicationChannel::InApp->value, $canais);
        $tipos = array_column($comunicacoes, 'type');
        $this->assertContains(CommunicationType::PendenciaAberta->value, $tipos);
        $this->assertContains(CommunicationType::Resultado->value, $tipos);

        // RN-002: a consulta é auditada.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'notificacoes',
            'event' => 'historico-consultado',
            'result' => 'sucesso',
            'subject_type' => $processo->getMorphClass(),
            'subject_id' => $processo->id,
        ]);
    }

    public function test_gestao_sem_consultar_solicitacoes_recebe_403_auditado(): void
    {
        $processo = $this->processo();
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        $this->actingAs($semPermissao, 'gestao')
            ->get(route('gestao.processos.comunicacoes', $processo))
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
        ]);
    }

    public function test_portal_dono_lista_historico_e_audita(): void
    {
        $dono = $this->portalUser();
        $processo = $this->processo($dono);
        $this->comunicacao($processo, CommunicationType::PendenciaAberta, CommunicationChannel::Email, CommunicationStatus::Enviado);

        $page = $this->actingAs($dono)
            ->get(route('portal.solicitacoes.comunicacoes', $processo))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('portal/solicitacoes/comunicacoes', $page['component']);
        $this->assertCount(1, $page['props']['comunicacoes']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'notificacoes',
            'event' => 'historico-consultado',
            'result' => 'sucesso',
            'subject_type' => $processo->getMorphClass(),
            'subject_id' => $processo->id,
        ]);
    }

    public function test_portal_nao_dono_recebe_403_auditado(): void
    {
        $dono = $this->portalUser();
        $estranho = $this->portalUser();
        $processo = $this->processo($dono);
        $this->comunicacao($processo, CommunicationType::PendenciaAberta, CommunicationChannel::Email);

        $this->actingAs($estranho)
            ->get(route('portal.solicitacoes.comunicacoes', $processo))
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
        ]);
    }

    public function test_error_message_visivel_na_gestao_e_oculto_no_portal_lgpd(): void
    {
        $dono = $this->portalUser();
        $processo = $this->processo($dono);
        // Falha real com diagnóstico interno (error_message).
        $this->comunicacao(
            $processo,
            CommunicationType::PendenciaAberta,
            CommunicationChannel::Email,
            CommunicationStatus::Falhou,
            erro: 'SMTP indisponível: connection refused',
        );

        // Gestão VÊ o error_message (diagnóstico operacional).
        $gestao = $this->actingAs($this->analista(), 'gestao')
            ->get(route('gestao.processos.comunicacoes', $processo))
            ->assertOk()
            ->viewData('page');
        $this->assertArrayHasKey('error_message', $gestao['props']['comunicacoes'][0]);
        $this->assertSame('SMTP indisponível: connection refused', $gestao['props']['comunicacoes'][0]['error_message']);

        // Portal NÃO expõe o error_message (LGPD — sem diagnóstico interno).
        // Guard 'web' explícito: o actingAs anterior (gestao) deixou o guard
        // padrão como gestao; sem isso, o usuário cairia no guard errado.
        $portal = $this->actingAs($dono, 'web')
            ->get(route('portal.solicitacoes.comunicacoes', $processo))
            ->assertOk()
            ->viewData('page');
        $this->assertArrayNotHasKey('error_message', $portal['props']['comunicacoes'][0]);
        // Mas o status honesto continua visível (falhou).
        $this->assertSame(CommunicationStatus::Falhou->value, $portal['props']['comunicacoes'][0]['status']);
    }
}
