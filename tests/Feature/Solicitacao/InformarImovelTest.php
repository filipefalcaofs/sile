<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\CompanyLinkRole;
use App\Enums\ViabilityRequestStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Informar o imóvel (HU-062) e a área utilizada (HU-063) do rascunho: grava o
 * polígono de 4 pontos (GeoJSON é a fonte), complemento (texto livre — HU-139
 * adiado), ponto de referência e indicadores; identifica o território reusando o
 * TerritoryService da Fase 4 (degrada honesto sem zona — nunca inventa); valida
 * a área declarada contra a do polígono com tolerância parametrizável (alerta,
 * não bloqueia — RN-004); e zera a simulação anterior (markSimulationStale —
 * RN-005). Só o dono edita, e apenas em rascunho (policy update, CA-04).
 */
class InformarImovelTest extends TestCase
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

    /**
     * Rascunho do usuário (nasce sem imóvel/área).
     */
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
     * Polígono de 4 pontos (quadrilátero fechado) em Salvador (~480 m²).
     *
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

    public function test_dono_informa_imovel(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);

        $this->actingAs($user)
            ->from('/portal/solicitacoes')
            ->put("/portal/solicitacoes/{$solicitacao->id}/imovel", $this->payload())
            ->assertRedirect('/portal/solicitacoes')
            ->assertSessionHas('status')
            ->assertSessionHas('territorio')
            ->assertSessionMissing('areaAlert');

        $fresh = $solicitacao->fresh();
        $this->assertSame('Em frente à praça central', $fresh->address_reference);
        $this->assertSame('Sala 2 do edifício comercial', $fresh->address_complement);
        $this->assertSame(100.0, (float) $fresh->used_area_m2);
        $this->assertTrue($fresh->has_independent_access);
        $this->assertFalse($fresh->is_virtual_office);
        $this->assertEquals($this->poligono(), $fresh->property_polygon_geojson);
    }

    public function test_zona_pendente_nao_inventa(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);

        $response = $this->actingAs($user)
            ->from('/portal/solicitacoes')
            ->put("/portal/solicitacoes/{$solicitacao->id}/imovel", $this->payload())
            ->assertSessionHas('territorio');

        // Sem base de zoneamento (pendente SEDUR): zona "indisponível", com motivo
        // explícito e SEM nome inventado.
        $territorio = session('territorio');
        $this->assertSame('indisponivel', $territorio['zona']['status']);
        $this->assertNull($territorio['zona']['nome']);
        $this->assertNotEmpty($territorio['zona']['motivo']);
    }

    public function test_area_divergente_alerta_sem_bloquear(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);

        // Área declarada muito maior que a do polígono (~480 m²) — inconsistência.
        $this->actingAs($user)
            ->from('/portal/solicitacoes')
            ->put("/portal/solicitacoes/{$solicitacao->id}/imovel", $this->payload(['used_area_m2' => 5000]))
            ->assertRedirect('/portal/solicitacoes')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('areaAlert');

        // Não bloqueou: a inconsistência mantida fica registrada (área salva).
        $this->assertSame(5000.0, (float) $solicitacao->fresh()->used_area_m2);

        // E registrada para o analista (auditada — RN-002).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'solicitacoes',
            'event' => 'imovel-area-inconsistente',
            'subject_type' => (new ViabilityRequest)->getMorphClass(),
            'subject_id' => $solicitacao->id,
        ]);
    }

    public function test_mudanca_de_imovel_zera_simulacao(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, [
            'simulated_at' => now(),
            'simulation_snapshot' => ['veredito' => 'pendente'],
            'simulation_resultado' => 'pendente',
        ]);

        $this->assertNotNull($solicitacao->simulated_at);

        $this->actingAs($user)
            ->from('/portal/solicitacoes')
            ->put("/portal/solicitacoes/{$solicitacao->id}/imovel", $this->payload());

        $fresh = $solicitacao->fresh();
        $this->assertNull($fresh->simulated_at);
        $this->assertNull($fresh->simulation_snapshot);
        $this->assertNull($fresh->simulation_resultado);
    }

    public function test_so_dono_em_rascunho_edita(): void
    {
        $owner = $this->portalUser();
        $stranger = $this->portalUser();
        $solicitacao = $this->draftFor($owner);

        // Terceiro não edita a solicitação alheia — 403 auditado (CA-04).
        $this->actingAs($stranger)
            ->put("/portal/solicitacoes/{$solicitacao->id}/imovel", $this->payload())
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);

        // Solicitação já protocolada não é mais editável (policy update).
        $protocolada = $this->draftFor($owner, [
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => 'VIA-2026-000099',
            'protocoled_at' => now(),
        ]);

        $this->actingAs($owner)
            ->put("/portal/solicitacoes/{$protocolada->id}/imovel", $this->payload())
            ->assertForbidden();
    }

    public function test_validacao_exige_poligono_referencia_e_area(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);

        $this->actingAs($user)
            ->from('/portal/solicitacoes')
            ->put("/portal/solicitacoes/{$solicitacao->id}/imovel", [
                'property_polygon_geojson' => ['type' => 'Polygon', 'coordinates' => [[[-38.51, -12.97]]]],
                'used_area_m2' => 0,
                'address_reference' => '',
            ])
            ->assertSessionHasErrors(['property_polygon_geojson.coordinates.0', 'used_area_m2', 'address_reference']);
    }
}
