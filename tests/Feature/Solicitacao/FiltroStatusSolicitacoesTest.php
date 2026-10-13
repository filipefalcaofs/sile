<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Filtro por estado em "Minhas solicitações": destino dos indicadores do
 * painel do cidadão. Valor inválido é ignorado; escopo do requerente mantido.
 */
class FiltroStatusSolicitacoesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    private function solicitacao(User $user, ViabilityRequestStatus $status, ?string $protocolo = null): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'requester_user_id' => $user->id,
            'status' => $status,
            'protocol_number' => $protocolo,
        ]);
    }

    public function test_filtra_pelo_estado_informado(): void
    {
        $user = $this->portalUser();
        $this->solicitacao($user, ViabilityRequestStatus::EmAnalise, 'VIA-2026-000001');
        $this->solicitacao($user, ViabilityRequestStatus::EmPendencia, 'VIA-2026-000002');
        $this->solicitacao($user, ViabilityRequestStatus::Deferida, 'VIA-2026-000003');

        $this->actingAs($user)
            ->get('/portal/solicitacoes?status=em_pendencia')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/solicitacoes/index')
                ->has('solicitacoes.data', 1)
                ->where('solicitacoes.data.0.status.value', 'em_pendencia')
                ->where('filters.status', 'em_pendencia')
                ->where('filters.status_label', ViabilityRequestStatus::EmPendencia->publicLabel()));
    }

    public function test_filtro_nao_mostra_processos_de_outro_requerente(): void
    {
        $user = $this->portalUser();
        $outro = $this->portalUser();
        $this->solicitacao($user, ViabilityRequestStatus::EmAnalise);
        $this->solicitacao($outro, ViabilityRequestStatus::EmAnalise);

        $this->actingAs($user)
            ->get('/portal/solicitacoes?status=em_analise')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('solicitacoes.data', 1));
    }

    public function test_estado_invalido_e_ignorado(): void
    {
        $user = $this->portalUser();
        $this->solicitacao($user, ViabilityRequestStatus::EmAnalise);
        $this->solicitacao($user, ViabilityRequestStatus::Deferida);

        foreach (['/portal/solicitacoes?status=xpto', '/portal/solicitacoes?status=EM_ANALISE', '/portal/solicitacoes?status[]=em_analise'] as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->has('solicitacoes.data', 2)
                    ->where('filters.status', '')
                    ->where('filters.status_label', null));
        }
    }

    public function test_combina_estado_com_busca(): void
    {
        $user = $this->portalUser();
        $this->solicitacao($user, ViabilityRequestStatus::EmAnalise, 'VIA-2026-000010');
        $this->solicitacao($user, ViabilityRequestStatus::EmAnalise, 'VIA-2026-000020');
        $this->solicitacao($user, ViabilityRequestStatus::Deferida, 'VIA-2026-000011');

        $this->actingAs($user)
            ->get('/portal/solicitacoes?status=em_analise&search=000010')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('solicitacoes.data', 1)
                ->where('solicitacoes.data.0.protocol_number', 'VIA-2026-000010'));
    }

    public function test_sem_estado_lista_tudo(): void
    {
        $user = $this->portalUser();
        $this->solicitacao($user, ViabilityRequestStatus::EmAnalise);
        $this->solicitacao($user, ViabilityRequestStatus::Deferida);

        $this->actingAs($user)
            ->get('/portal/solicitacoes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('solicitacoes.data', 2)
                ->where('filters.status', ''));
    }
}
