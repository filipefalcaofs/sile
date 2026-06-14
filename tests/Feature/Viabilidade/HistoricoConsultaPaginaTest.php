<?php

namespace Tests\Feature\Viabilidade;

use App\Models\User;
use App\Models\ViabilityQuery;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Página AUTENTICADA do histórico de viabilidade (HU-060, UI 07-09): agora que a
 * página existe (resources/js/pages/portal/viabilidade/historico.tsx), o teste
 * reativa a asserção do componente Inertia que o 07-07 deixou pendente. Reforça
 * o contrato server-side da tela: o componente renderizado, o escopo do dono
 * (forUser — precedente "Minhas empresas") e a exigência de autenticação.
 */
class HistoricoConsultaPaginaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Cidadão do portal com termo LGPD aceito — precedente "Minhas empresas".
     */
    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    public function test_pagina_do_historico_renderiza_consultas_do_dono(): void
    {
        // HU-060: a página renderiza o componente Inertia do histórico com APENAS
        // as consultas do próprio usuário (escopo do dono — as de terceiro nunca
        // aparecem).
        $dono = $this->portalUser();
        $outro = $this->portalUser();

        ViabilityQuery::factory()->for($dono)->create();
        ViabilityQuery::factory()->for($dono)->create();
        ViabilityQuery::factory()->for($outro)->create();

        $this->actingAs($dono)
            ->get('/portal/viabilidade/historico')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/viabilidade/historico')
                ->has('consultas.data', 2)
                ->where('consultas.total', 2));
    }

    public function test_pagina_do_historico_exige_autenticacao(): void
    {
        // A rota da página é autenticada (auth:web + verified + lgpd.accepted): o
        // visitante é redirecionado ao login do portal.
        $this->get('/portal/viabilidade/historico')->assertRedirect('/portal/login');
    }
}
