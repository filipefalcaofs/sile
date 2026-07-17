<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\CompanyLinkRole;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Captura da pergunta "será sede de escritório virtual?" (wants_virtual_office_hq)
 * no mesmo passo do imóvel (HU-062) do rascunho — reusa a estrutura de
 * tests/Feature/Solicitacao/InformarImovelTest.php.
 */
class SedePerguntaPortalTest extends TestCase
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
            'used_area_m2' => null,
            'address_reference' => null,
            'property_polygon_geojson' => null,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function poligono(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.5108, -12.9711],
                [-38.5108, -12.9709],
                [-38.5106, -12.9709],
                [-38.5106, -12.9711],
                [-38.5108, -12.9711],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'property_polygon_geojson' => $this->poligono(),
            'used_area_m2' => 100,
            'address_reference' => 'Em frente à praça central',
            'address_street' => 'Rua das Flores',
            'address_number' => '123',
            'address_complement' => 'Sala 2 do edifício comercial',
            'address_neighborhood' => 'Barra',
            'address_zip' => '40140000',
            'is_virtual_office' => false,
            'is_public_area' => false,
            'has_independent_access' => true,
        ], $overrides);
    }

    public function test_dono_informa_que_sera_sede_de_escritorio_virtual(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);

        $this->actingAs($user)
            ->from('/portal/solicitacoes')
            ->put(
                "/portal/solicitacoes/{$solicitacao->id}/imovel",
                $this->payload(['wants_virtual_office_hq' => true])
            )
            ->assertRedirect('/portal/solicitacoes')
            ->assertSessionHas('status');

        $this->assertTrue($solicitacao->fresh()->wants_virtual_office_hq);
    }
}
