<?php

namespace Tests\Feature\Portal;

use App\Enums\CompanyLinkRole;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\Company;
use App\Models\Procuration;
use App\Models\User;
use App\Models\ViabilityQuery;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Painel do cidadão (Meu Painel) — agregador das HUs do portal. Prova dado real
 * + escopo do usuário efetivo + linguagem pública, sem fachada.
 */
class PainelCidadaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Cidadão habilitado a navegar no portal (papel + termo LGPD aceito). */
    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    /** Empresa com vínculo ATIVO do usuário. */
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

    public function test_painel_exibe_indicadores_reais(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);

        // Em andamento (contam): protocolada + em análise = 2.
        ViabilityRequest::factory()->protocoled()->create([
            'requester_user_id' => $user->id,
            'company_id' => $company->id,
        ]);
        ViabilityRequest::factory()->create([
            'requester_user_id' => $user->id,
            'company_id' => $company->id,
            'status' => ViabilityRequestStatus::EmAnalise,
        ]);
        // Não contam em "em andamento": rascunho e deferida.
        ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'company_id' => $company->id,
        ]);
        ViabilityRequest::factory()->create([
            'requester_user_id' => $user->id,
            'company_id' => $company->id,
            'status' => ViabilityRequestStatus::Deferida,
        ]);

        ViabilityQuery::factory()->count(2)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get('/portal/painel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/dashboard')
                ->where('indicadores.em_andamento', 2)
                ->where('indicadores.empresas', 1)
                ->where('indicadores.consultas', 2)
                ->where('emRepresentacao', false));
    }

    public function test_painel_lista_solicitacoes_recentes_limitadas_e_publicas(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);

        // 6 solicitações com datas decrescentes (a mais recente primeiro).
        foreach (range(0, 5) as $i) {
            ViabilityRequest::factory()->protocoled()->create([
                'requester_user_id' => $user->id,
                'company_id' => $company->id,
                'protocol_number' => sprintf('VIA-2026-%06d', $i),
                'created_at' => now()->subDays($i),
            ]);
        }

        $this->actingAs($user)
            ->get('/portal/painel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Limite default (config sile.ui.painel.solicitacoes_recentes = 5).
                ->has('solicitacoesRecentes', 5)
                // Mais recente primeiro (i = 0).
                ->where('solicitacoesRecentes.0.protocol_number', 'VIA-2026-000000')
                // Linguagem pública do status (publicLabel), nunca o label técnico.
                ->where('solicitacoesRecentes.0.status.public_label', 'Recebida — em processamento'));
    }

    public function test_painel_atencao_traz_pendencias_abertas_e_rascunhos_do_efetivo(): void
    {
        $user = $this->portalUser();
        $other = $this->portalUser();
        $company = $this->companyLinkedTo($user);

        // Pendência ABERTA do usuário (aparece).
        $req = ViabilityRequest::factory()->protocoled()->create([
            'requester_user_id' => $user->id,
            'company_id' => $company->id,
            'protocol_number' => 'VIA-2026-000100',
        ]);
        AnalysisPendency::factory()->create(['viability_request_id' => $req->id]);

        // Pendência RESPONDIDA do usuário (NÃO aparece).
        AnalysisPendency::factory()->respondida()->create(['viability_request_id' => $req->id]);

        // Pendência aberta de OUTRO usuário (NÃO aparece — escopo).
        $otherCompany = $this->companyLinkedTo($other);
        $otherReq = ViabilityRequest::factory()->protocoled()->create([
            'requester_user_id' => $other->id,
            'company_id' => $otherCompany->id,
        ]);
        AnalysisPendency::factory()->create(['viability_request_id' => $otherReq->id]);

        // Rascunho do usuário (aparece).
        ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        // Rascunho de OUTRO usuário (NÃO aparece — escopo).
        ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $other->id,
            'company_id' => $otherCompany->id,
        ]);

        $this->actingAs($user)
            ->get('/portal/painel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('atencao.pendencias', 1)
                ->where('atencao.pendencias.0.solicitacao_id', $req->id)
                ->where('atencao.pendencias.0.protocol_number', 'VIA-2026-000100')
                ->has('atencao.rascunhos', 1));
    }

    public function test_painel_vazio_zera_indicadores_e_listas(): void
    {
        $user = $this->portalUser();

        $this->actingAs($user)
            ->get('/portal/painel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('indicadores.em_andamento', 0)
                ->where('indicadores.empresas', 0)
                ->where('indicadores.consultas', 0)
                ->has('atencao.pendencias', 0)
                ->has('atencao.rascunhos', 0)
                ->has('solicitacoesRecentes', 0)
                ->where('emRepresentacao', false));
    }

    public function test_painel_em_representacao_mostra_do_representado_sem_vazar(): void
    {
        $grantor = $this->portalUser();
        $attorney = $this->portalUser();

        $proc = Procuration::factory()->create([
            'grantor_user_id' => $grantor->id,
            'attorney_user_id' => $attorney->id,
        ]);

        // Dados do REPRESENTADO (devem aparecer).
        $grantorCompany = $this->companyLinkedTo($grantor);
        $grantorReq = ViabilityRequest::factory()->protocoled()->create([
            'requester_user_id' => $grantor->id,
            'company_id' => $grantorCompany->id,
            'protocol_number' => 'VIA-2026-000200',
        ]);

        // Dados do PROCURADOR (NÃO devem aparecer no painel do representado).
        $attorneyCompany = $this->companyLinkedTo($attorney);
        ViabilityRequest::factory()->protocoled()->create([
            'requester_user_id' => $attorney->id,
            'company_id' => $attorneyCompany->id,
            'protocol_number' => 'VIA-2026-000999',
        ]);
        ViabilityQuery::factory()->count(3)->create(['user_id' => $attorney->id]);

        $this->actingAs($attorney)
            ->withSession(['acting_procuration_id' => $proc->id])
            ->get('/portal/painel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('emRepresentacao', true)
                // Solicitações/empresas do representado.
                ->where('indicadores.em_andamento', 1)
                ->where('indicadores.empresas', 1)
                ->has('solicitacoesRecentes', 1)
                ->where('solicitacoesRecentes.0.id', $grantorReq->id)
                // Consultas (pessoais do procurador) omitidas em representação.
                ->where('indicadores.consultas', null));
    }
}
