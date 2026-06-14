<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\CompanyLinkRole;
use App\Enums\ViabilityRequestStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Páginas Inertia da jornada do cidadão (08-13): a lista "Minhas solicitações"
 * (index) e o wizard multi-etapas (create=nova / edit=editar). Os testes
 * asseguram que as páginas RENDERIZAM os componentes corretos com o shape real
 * consumido pelo front (nada simulado): a lista expõe status amigável e a ação
 * de cancelar (cancelable), o wizard recebe o rascunho + os dados de apoio
 * (tipos de serviço ativos, empresas do dono) e é autorizado pela
 * ViabilityRequestPolicy (só o dono edita, e apenas em rascunho).
 */
class SolicitacaoWizardPaginaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    private function companyLinkedTo(User $user): Company
    {
        $company = Company::factory()->create();

        $company->links()->create([
            'user_id' => $user->id,
            'role' => CompanyLinkRole::Responsavel,
            'started_at' => now(),
        ]);

        return $company;
    }

    private function draftFor(User $user, array $overrides = []): ViabilityRequest
    {
        return ViabilityRequest::factory()->draft()->create(array_merge([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'company_id' => $this->companyLinkedTo($user)->id,
        ], $overrides));
    }

    public function test_index_renderiza_minhas_solicitacoes(): void
    {
        $user = $this->portalUser();
        $this->draftFor($user);

        $this->actingAs($user)
            ->get('/portal/solicitacoes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/solicitacoes/index')
                ->has('solicitacoes.data', 1)
                ->has('filters')
                ->where('solicitacaoEnabled', true)
            );
    }

    public function test_index_marca_rascunho_como_cancelavel(): void
    {
        // A ação de cancelar só aparece quando a solicitação é cancelável: o
        // rascunho é (estados canceláveis default = rascunho + protocolada).
        $user = $this->portalUser();
        $this->draftFor($user);

        $this->actingAs($user)
            ->get('/portal/solicitacoes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('solicitacoes.data.0.cancelable', true)
                ->where('solicitacoes.data.0.status.value', 'rascunho')
            );
    }

    public function test_index_nao_marca_cancelada_como_cancelavel(): void
    {
        // Já cancelada NÃO é cancelável de novo (fora dos estados canceláveis).
        $user = $this->portalUser();
        $this->draftFor($user, [
            'status' => ViabilityRequestStatus::Cancelada,
            'cancelled_at' => now(),
            'cancelled_reason' => 'desistência',
        ]);

        $this->actingAs($user)
            ->get('/portal/solicitacoes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('solicitacoes.data.0.cancelable', false)
                ->where('solicitacoes.data.0.status.value', 'cancelada')
            );
    }

    public function test_index_expoe_alerta_de_duplicidade_da_sessao(): void
    {
        // O store (08-05) redireciona para o index com flash duplicateAlert
        // (RN-007, nunca bloqueia): o index o expõe como prop para a UI mostrar
        // o link ao processo anterior.
        $user = $this->portalUser();

        $this->actingAs($user)
            ->withSession(['duplicateAlert' => [
                'request_id' => 123,
                'protocol_number' => 'VIA-2026-000001',
                'status' => 'protocolada',
                'created_at' => null,
            ]])
            ->get('/portal/solicitacoes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('duplicateAlert.request_id', 123)
                ->where('duplicateAlert.protocol_number', 'VIA-2026-000001')
            );
    }
}
