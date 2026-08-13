<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\CompanyLinkRole;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
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

    public function test_nova_renderiza_o_wizard_com_dados_de_apoio(): void
    {
        // GET solicitacoes/nova (portal.solicitacoes.create) renderiza o wizard
        // para um NOVO rascunho (sem solicitação ainda) com os dados de apoio.
        $user = $this->portalUser();

        $this->actingAs($user)
            ->get('/portal/solicitacoes/nova')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/solicitacoes/wizard')
                ->where('solicitacao', null)
                ->has('serviceTypes')
                ->has('companies')
                ->where('solicitacaoEnabled', true)
            );
    }

    public function test_nova_so_lista_tipos_ativos_e_empresas_do_dono(): void
    {
        $user = $this->portalUser();
        $this->companyLinkedTo($user);
        Company::factory()->create(); // empresa de terceiro, sem vínculo

        ViabilityServiceType::factory()->create();
        ViabilityServiceType::factory()->inactive()->create();

        $this->actingAs($user)
            ->get('/portal/solicitacoes/nova')
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/solicitacoes/wizard')
                ->has('serviceTypes', 1)
                ->has('companies', 1)
            );
    }

    public function test_editar_renderiza_o_wizard_do_rascunho_do_dono(): void
    {
        // GET solicitacoes/{solicitacao}/editar (portal.solicitacoes.edit)
        // renderiza o wizard com o rascunho do dono — DISTINTA da rota de
        // protocolo/consulta solicitacoes/{solicitacao} (08-11).
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);
        $solicitacao->cnaes()->attach(Cnae::factory()->create()->id, ['is_primary' => true]);

        $this->actingAs($user)
            ->get("/portal/solicitacoes/{$solicitacao->id}/editar")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/solicitacoes/wizard')
                ->where('solicitacao.id', $solicitacao->id)
                ->where('solicitacao.status.value', 'rascunho')
                ->has('solicitacao.cnaes', 1)
                ->has('anexosConfig')
                ->where('simulacaoEnabled', true)
            );
    }

    public function test_editar_bloqueia_terceiro(): void
    {
        // CA-04: terceiro não edita o rascunho alheio (policy update → 403).
        $owner = $this->portalUser();
        $stranger = $this->portalUser();
        $solicitacao = $this->draftFor($owner);

        $this->actingAs($stranger)
            ->get("/portal/solicitacoes/{$solicitacao->id}/editar")
            ->assertForbidden();
    }

    public function test_editar_bloqueia_quando_protocolada(): void
    {
        // Protocolada não é editável (policy update = dono + rascunho): o wizard
        // só serve para rascunho; a consulta da protocolada é a página show.
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, [
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => 'VIA-2026-000050',
            'protocoled_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/portal/solicitacoes/{$solicitacao->id}/editar")
            ->assertForbidden();
    }
}
