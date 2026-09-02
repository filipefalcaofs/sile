<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\CompanyLinkRole;
use App\Models\Company;
use App\Models\Parameter;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Pergunta geral "deseja ser abrigado de escritório virtual?"
 * (wants_virtual_office_tenant), no mesmo passo do imóvel (HU-062) do
 * rascunho — reusa a estrutura de SedePerguntaPortalTest.
 */
class PerguntaGeralPortalTest extends TestCase
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

    /**
     * O payload do wizard entrega a resposta atual da pergunta geral e o texto
     * dela, que e parametrizavel (RN-EV-01, [OPEN-EV-8]). Sem isso a tela nao
     * tem o que renderizar e a regra fica inalcancavel.
     */
    public function test_payload_do_wizard_entrega_a_resposta_e_o_texto_da_pergunta(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);

        $response = $this->actingAs($user)->get("/portal/solicitacoes/{$solicitacao->id}/editar");

        $response->assertInertia(fn ($page) => $page
            ->where('solicitacao.indicators.wants_virtual_office_tenant', null)
            ->where(
                'solicitacao.escritorio_virtual.pergunta_geral',
                'Deseja ser abrigado de escritório virtual?',
            ));
    }

    /**
     * O texto da pergunta e administravel (Settings::get) — sem deploy, a SEDUR
     * pode ajustar a redacao exibida ao requerente.
     */
    public function test_texto_da_pergunta_e_parametrizavel(): void
    {
        // Cria a linha do catalogo em vez de dar update: este teste nao semeia o
        // ParameterSeeder, entao um update casaria ZERO linhas em silencio e o
        // Settings::get cairia no default do config — o teste passaria sem
        // provar nada. Mesma disciplina do irmao em RecusaAbrigoEmInscricaoTravadaTest.
        Parameter::factory()->create([
            'key' => 'analise.escritorio_virtual.pergunta_geral',
            'group' => 'analise',
            'type' => 'string',
            'value' => 'Texto customizado da pergunta geral?',
        ]);

        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);

        $response = $this->actingAs($user)->get("/portal/solicitacoes/{$solicitacao->id}/editar");

        $response->assertInertia(fn ($page) => $page
            ->where(
                'solicitacao.escritorio_virtual.pergunta_geral',
                'Texto customizado da pergunta geral?',
            ));
    }

    /**
     * Resposta afirmativa (true) e persistida pelo passo do imovel.
     */
    public function test_resposta_afirmativa_e_persistida_pelo_passo_do_imovel(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);

        $this->actingAs($user)
            ->from('/portal/solicitacoes')
            ->put(
                "/portal/solicitacoes/{$solicitacao->id}/imovel",
                $this->payload(['wants_virtual_office_tenant' => true])
            )
            ->assertRedirect('/portal/solicitacoes')
            ->assertSessionHas('status');

        $this->assertTrue($solicitacao->fresh()->wants_virtual_office_tenant);
    }

    /**
     * Resposta afirmativa (true) e preservada por um PUT seguinte SEM a chave
     * wants_virtual_office_tenant — e o caso que de fato trava a correcao do
     * controller (I4: has() em vez de boolean() direto). A prova precisa
     * partir de true: se partisse de false, ausencia de chave e false
     * explicito produziriam o mesmo resultado tanto com has() (correto,
     * mantem o valor anterior) quanto com boolean() isolado (regressao,
     * ausencia vira false) — o teste passaria nos dois casos e nao provaria
     * nada. Partindo de true, so o has() preserva o valor; boolean() isolado
     * apagaria para false e a asserção final quebraria. Quem "simplificar"
     * o has() para boolean() direto volta a apagar a resposta a cada
     * gravacao, e este teste acusa.
     */
    public function test_resposta_afirmativa_e_preservada_quando_put_seguinte_omite_a_chave(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);

        $this->actingAs($user)
            ->from('/portal/solicitacoes')
            ->put(
                "/portal/solicitacoes/{$solicitacao->id}/imovel",
                $this->payload(['wants_virtual_office_tenant' => true])
            )
            ->assertRedirect('/portal/solicitacoes')
            ->assertSessionHas('status');

        $this->assertTrue($solicitacao->fresh()->wants_virtual_office_tenant);

        // Segundo PUT sem a chave wants_virtual_office_tenant no payload.
        $this->actingAs($user)
            ->from('/portal/solicitacoes')
            ->put(
                "/portal/solicitacoes/{$solicitacao->id}/imovel",
                $this->payload()
            )
            ->assertRedirect('/portal/solicitacoes')
            ->assertSessionHas('status');

        $this->assertTrue($solicitacao->fresh()->wants_virtual_office_tenant);
    }

    /**
     * Resposta negativa (false) e persistida e distinguivel de null (ainda
     * nao respondido): o campo tem tres estados, e gravar false precisa
     * chegar como false, nao ficar preso no default nulo do rascunho.
     */
    public function test_resposta_negativa_e_persistida_e_distinta_de_ausencia_de_resposta(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user);

        $this->assertNull($solicitacao->wants_virtual_office_tenant);

        $this->actingAs($user)
            ->from('/portal/solicitacoes')
            ->put(
                "/portal/solicitacoes/{$solicitacao->id}/imovel",
                $this->payload(['wants_virtual_office_tenant' => false])
            )
            ->assertRedirect('/portal/solicitacoes')
            ->assertSessionHas('status');

        $this->assertFalse($solicitacao->fresh()->wants_virtual_office_tenant);
    }
}
