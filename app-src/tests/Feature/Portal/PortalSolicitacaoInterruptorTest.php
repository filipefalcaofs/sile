<?php

namespace Tests\Feature\Portal;

use App\Http\Middleware\EnsurePortalSolicitacaoEnabled;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Interruptor temporário (SILE_PORTAL_SOLICITACAO) das jornadas de criação do
 * portal: com ele desligado, solicitação inicial e renovação ficam bloqueadas
 * com aviso; o acompanhamento de processos existentes segue funcionando.
 */
class PortalSolicitacaoInterruptorTest extends TestCase
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

    public function test_desligado_bloqueia_wizard_de_nova_solicitacao_com_aviso(): void
    {
        config(['sile.features.portal_solicitacao' => false]);

        $this->actingAs($this->portalUser())
            ->get('/portal/solicitacoes/nova')
            ->assertRedirect(route('portal.dashboard'))
            ->assertSessionHas('warning', EnsurePortalSolicitacaoEnabled::MENSAGEM);
    }

    public function test_desligado_bloqueia_criacao_de_solicitacao(): void
    {
        config(['sile.features.portal_solicitacao' => false]);

        $this->actingAs($this->portalUser())
            ->post('/portal/solicitacoes')
            ->assertRedirect(route('portal.dashboard'));

        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_desligado_bloqueia_a_renovacao(): void
    {
        config(['sile.features.portal_solicitacao' => false]);

        $this->actingAs($this->portalUser())
            ->get('/portal/renovacao')
            ->assertRedirect(route('portal.dashboard'))
            ->assertSessionHas('warning', EnsurePortalSolicitacaoEnabled::MENSAGEM);
    }

    public function test_desligado_responde_503_em_json(): void
    {
        config(['sile.features.portal_solicitacao' => false]);

        $this->actingAs($this->portalUser())
            ->postJson('/portal/renovacao/consultar-cnpj')
            ->assertStatus(503)
            ->assertJson(['message' => EnsurePortalSolicitacaoEnabled::MENSAGEM]);
    }

    public function test_desligado_mantem_a_lista_de_solicitacoes_e_o_painel(): void
    {
        config(['sile.features.portal_solicitacao' => false]);

        $user = $this->portalUser();

        $this->actingAs($user)->get('/portal/solicitacoes')->assertOk();
        $this->actingAs($user)->get('/portal/painel')->assertOk();
    }

    public function test_ligado_o_wizard_abre_normalmente(): void
    {
        config(['sile.features.portal_solicitacao' => true]);

        $this->actingAs($this->portalUser())
            ->get('/portal/solicitacoes/nova')
            ->assertOk();
    }
}
