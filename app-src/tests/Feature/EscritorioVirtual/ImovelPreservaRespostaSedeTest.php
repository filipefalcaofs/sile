<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Preservação da resposta de intenção de sede ao regravar o passo do imóvel
 * (simétrico a ImovelPreservaRespostaAbrigadoTest, achado 6 da revisão
 * final): a tela `etapa-imovel.tsx` também não envia
 * `wants_virtual_office_hq` hoje. Ausência da chave no payload significa "o
 * formulário não perguntou desta vez", nunca "apague o que havia" — a
 * correção anterior tratou só `wants_virtual_office_tenant` e deixou `hq`
 * gravando `$request->boolean(...)` incondicional, que zera a resposta a
 * cada save do passo do imóvel.
 */
class ImovelPreservaRespostaSedeTest extends TestCase
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

    /**
     * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
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

    public function test_regravar_o_passo_do_imovel_sem_a_chave_preserva_a_resposta_de_sede_anterior(): void
    {
        $user = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'wants_virtual_office_hq' => true,
        ]);

        // Payload real da tela: sem 'wants_virtual_office_hq' (a pergunta
        // ainda não existe na interface do portal).
        $this->actingAs($user)
            ->put(route('portal.solicitacoes.imovel', $solicitacao), [
                'property_polygon_geojson' => $this->poligono(),
                'used_area_m2' => 80.0,
                'address_reference' => 'Ao lado da farmácia.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($solicitacao->refresh()->wants_virtual_office_hq);
    }
}
