<?php

namespace Tests\Feature\Renovacao;

use App\Enums\CompanyLinkRole;
use App\Enums\TvlKind;
use App\Enums\ViabilityRequestStatus;
use App\Models\Company;
use App\Models\ServiceDeclaration;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RenovacaoPortalTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_lista_so_os_tvls_das_empresas_do_cidadao(): void
    {
        $cidadao = $this->portalUser();
        $empresa = $this->empresaDe($cidadao);
        $elegivel = $this->tvl($empresa, TvlKind::Provisoria);
        $definitivo = $this->tvl($empresa, TvlKind::Definitiva);
        $alheio = $this->tvl(Company::factory()->create(), TvlKind::Provisoria);

        $this->actingAs($cidadao)
            ->get('/portal/renovacao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/renovacao/index')
                ->has('tvls', 2)
                ->where('tvls.0.id', $elegivel->id)
                ->where('tvls.0.elegivel', true)
                ->where('tvls.1.id', $definitivo->id)
                ->where('tvls.1.elegivel', false)
                ->where('tvls.1.motivo', 'Viabilidade definitiva não pode ser renovada.')
            );

        $this->assertNotSame($alheio->company_id, $empresa->id);
    }

    public function test_wizard_de_tvl_elegivel_traz_declaracoes(): void
    {
        $cidadao = $this->portalUser();
        $empresa = $this->empresaDe($cidadao);
        $origem = $this->tvl($empresa, TvlKind::Provisoria);
        $tipo = ViabilityServiceType::query()->where('code', 'renovacao-tvl')->firstOrFail();
        ServiceDeclaration::factory()->create([
            'service_type_id' => $tipo->id,
            'body' => 'Declaro que o endereço permanece o mesmo.',
            'required' => true,
        ]);

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/{$origem->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/renovacao/wizard')
                ->where('origem.id', $origem->id)
                ->has('declaracoes', 1)
                ->where('contato.email', $empresa->email)
            );
    }

    public function test_wizard_de_tvl_inelegivel_volta_com_motivo(): void
    {
        $cidadao = $this->portalUser();
        $origem = $this->tvl($this->empresaDe($cidadao), TvlKind::Definitiva);

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/{$origem->id}")
            ->assertRedirect('/portal/renovacao')
            ->assertSessionHas('error', 'Viabilidade definitiva não pode ser renovada.');
    }

    public function test_protocolar_renovacao_pelo_portal(): void
    {
        $cidadao = $this->portalUser();
        $origem = $this->tvl($this->empresaDe($cidadao), TvlKind::Provisoria);
        $tipo = ViabilityServiceType::query()->where('code', 'renovacao-tvl')->firstOrFail();
        $declaracao = ServiceDeclaration::factory()->create([
            'service_type_id' => $tipo->id,
            'required' => true,
        ]);

        $this->actingAs($cidadao)
            ->post("/portal/renovacao/{$origem->id}", [
                'email' => 'contato@example.com',
                'phone' => '7133334444',
                'mobile' => '71988887777',
                'declaration_ids' => [$declaracao->id],
            ])
            ->assertRedirect();

        $renovacao = ViabilityRequest::query()->where('renews_viability_request_id', $origem->id)->first();

        $this->assertNotNull($renovacao);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $renovacao->status);
    }

    public function test_cidadao_nao_renova_tvl_de_outra_empresa(): void
    {
        $cidadao = $this->portalUser();
        $origem = $this->tvl(Company::factory()->create(), TvlKind::Provisoria);

        $this->actingAs($cidadao)
            ->get("/portal/renovacao/{$origem->id}")
            ->assertForbidden();
    }

    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    private function empresaDe(User $user): Company
    {
        $empresa = Company::factory()->create();
        $empresa->links()->create([
            'user_id' => $user->id,
            'role' => CompanyLinkRole::Responsavel,
            'started_at' => now(),
        ]);

        return $empresa;
    }

    private function tvl(Company $empresa, TvlKind $tipo): ViabilityRequest
    {
        ViabilityServiceType::query()->firstOrCreate(
            ['code' => 'renovacao-tvl'],
            ['name' => 'Renovação de TVL', 'active' => true],
        );

        $origem = ViabilityRequest::factory()->create([
            'company_id' => $empresa->id,
            'address_street' => 'Rua do TVL',
        ]);
        $origem->forceFill(['status' => ViabilityRequestStatus::Deferida])->save();
        ViabilityDecision::factory()->create([
            'viability_request_id' => $origem->id,
            'tvl_kind' => $tipo,
            'tvl_product_number' => 'TVL-'.$origem->id,
            'tvl_valid_until' => '2027-09-28',
        ]);

        return $origem->fresh();
    }
}
