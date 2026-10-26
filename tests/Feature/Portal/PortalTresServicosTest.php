<?php

namespace Tests\Feature\Portal;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * O portal do cidadão fica com renovar viabilidade, consultar processo e
 * responder convite. Os demais serviços respondem 404 enquanto a chave
 * features.portal_servicos_extras estiver desligada.
 */
class PortalTresServicosTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->chaveExtras(false);
    }

    private function chaveExtras(bool $ligada): void
    {
        config(['viabiliza.features.portal_servicos_extras' => $ligada]);
        Cache::forget('viabiliza.parameters.features.portal_servicos_extras');
    }

    private function cidadao(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    public function test_servicos_retirados_respondem_404(): void
    {
        $user = $this->cidadao();

        foreach ([
            '/portal/viabilidade',
            '/portal/viabilidade/historico',
            '/portal/empresas',
            '/portal/empresas/cadastrar',
            '/portal/procuracoes',
            '/portal/solicitacoes',
            '/portal/solicitacoes/nova',
            '/portal/cnaes',
        ] as $url) {
            $this->actingAs($user)->get($url)->assertNotFound();
        }

        $this->actingAs($user)->postJson('/portal/renovacao/consultar-cnpj', ['cnpj' => '00000000000191'])->assertNotFound();
        $this->actingAs($user)->postJson('/portal/renovacao/legado/consultar', [])->assertNotFound();
        $this->actingAs($user)->postJson('/portal/empresas', [])->assertNotFound();
    }

    public function test_servicos_mantidos_respondem(): void
    {
        $user = $this->cidadao();

        foreach ([
            '/portal/painel',
            '/portal/renovacao',
            '/portal/notificacoes',
            '/portal/conta/perfil',
            '/portal/acessos',
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_chave_ligada_religa_os_retirados(): void
    {
        $this->chaveExtras(true);

        $this->actingAs($this->cidadao())->get('/portal/empresas')->assertOk();
    }
}
