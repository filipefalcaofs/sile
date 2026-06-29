<?php

namespace Tests\Feature\Portal;

use App\Enums\CompanyLinkRole;
use App\Enums\ViabilityRequestStatus;
use App\Models\Company;
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
}
