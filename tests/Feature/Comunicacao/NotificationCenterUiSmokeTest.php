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
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Smoke do contrato página↔props da UI da central in-app e do histórico (11-09):
 * garante que as páginas Inertia REAIS renderizam o componente correto com as
 * props que as telas React consomem — central de notificações (HU-090) nos dois
 * ambientes com o badge real (shared prop notificacoes.nao_lidas), a central de
 * pendências do requerente derivada de "Minhas solicitações" (HU-091) e o
 * histórico unificado de comunicações por processo (HU-096) no portal e na
 * gestão. As asserções de comportamento (escopo, anti-IDOR, auditoria, LGPD)
 * já vivem em NotificationCenterTest/ComunicacaoHistoricoTest (11-08); aqui
 * validamos só o casamento entre as páginas do 11-09 e o backend.
 */
class NotificationCenterUiSmokeTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    private function gestaoUser(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Notificação real no canal database nativo do usuário (espelha o que o
     * toDatabase() das Notifications de processo grava: title/summary/url).
     */
    private function notificar(User $user, bool $lida = false): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\PendenciaSolicitadaNotification',
            'data' => [
                'title' => 'Nova pendência na sua solicitação',
                'summary' => 'Envie o IPTU atualizado.',
                'url' => 'https://sile.test/portal/solicitacoes/1/pendencias',
            ],
            'read_at' => $lida ? now() : null,
        ]);
    }

    private function processo(?User $owner = null): ViabilityRequest
    {
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

    private function comunicacao(ViabilityRequest $processo): Communication
    {
        return Communication::factory()->create([
            'viability_request_id' => $processo->id,
            'type' => CommunicationType::PendenciaAberta,
            'channel' => CommunicationChannel::InApp,
            'status' => CommunicationStatus::Enviado,
        ]);
    }

    public function test_central_de_notificacoes_do_portal_renderiza_componente_e_badge(): void
    {
        $user = $this->portalUser();
        $this->notificar($user);
        $this->notificar($user, lida: true);

        $this->actingAs($user)
            ->get(route('portal.notificacoes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/notificacoes/index')
                ->has('lista.data', 2)
                ->has('lista.links')
                ->where('notificacoes.nao_lidas', 1));
    }

    public function test_central_de_notificacoes_da_gestao_renderiza_componente_e_badge(): void
    {
        $user = $this->gestaoUser();
        $this->notificar($user);
        $this->notificar($user);

        $this->actingAs($user, 'gestao')
            ->get(route('gestao.notificacoes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/notificacoes/index')
                ->has('lista.data', 2)
                ->where('notificacoes.nao_lidas', 2));
    }

    public function test_central_de_pendencias_do_requerente_lista_em_minhas_solicitacoes(): void
    {
        $user = $this->portalUser();

        $processo = $this->processo($user);
        $processo->forceFill(['status' => ViabilityRequestStatus::EmPendencia])->save();

        $this->actingAs($user)
            ->get(route('portal.solicitacoes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/solicitacoes/index')
                ->has('solicitacoes.data', 1)
                ->where('solicitacoes.data.0.status.value', ViabilityRequestStatus::EmPendencia->value));
    }

    public function test_historico_de_comunicacoes_do_portal_renderiza_componente(): void
    {
        $dono = $this->portalUser();
        $processo = $this->processo($dono);
        $this->comunicacao($processo);

        $this->actingAs($dono)
            ->get(route('portal.solicitacoes.comunicacoes', $processo))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/solicitacoes/comunicacoes')
                ->where('processo.id', $processo->id)
                ->has('comunicacoes', 1));
    }

    public function test_historico_de_comunicacoes_da_gestao_renderiza_componente(): void
    {
        $processo = $this->processo();
        $this->comunicacao($processo);

        $this->actingAs($this->gestaoUser(), 'gestao')
            ->get(route('gestao.processos.comunicacoes', $processo))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/processos/comunicacoes')
                ->where('processo.id', $processo->id)
                ->has('comunicacoes', 1));
    }
}
