<?php

namespace Tests\Feature\Portal;

use App\Http\Middleware\EnsurePortalServicosEnabled;
use App\Models\Parameter;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Interruptor parametrizável (features.portal_servicos) de todos os serviços
 * do portal. Desligado, consulta, solicitação, renovação, empresas e
 * procurações bloqueiam com aviso. Painel, notificações e conta seguem.
 */
class PortalServicosInterruptorTest extends TestCase
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

    private function desligar(): void
    {
        config(['viabiliza.features.portal_servicos' => false]);
        Cache::forget('viabiliza.parameters.features.portal_servicos');
    }

    public function test_desligado_bloqueia_os_servicos_do_portal_com_aviso(): void
    {
        $this->desligar();

        $user = $this->portalUser();

        foreach ([
            '/portal/viabilidade',
            '/portal/viabilidade/historico',
            '/portal/empresas',
            '/portal/solicitacoes',
            '/portal/solicitacoes/nova',
            '/portal/renovacao',
            '/portal/procuracoes',
        ] as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertRedirect(route('portal.dashboard'))
                ->assertSessionHas('warning', EnsurePortalServicosEnabled::MENSAGEM);
        }
    }

    public function test_desligado_visitante_na_consulta_volta_para_a_landing(): void
    {
        $this->desligar();

        $this->get('/portal/viabilidade')
            ->assertRedirect(route('home'))
            ->assertSessionHas('warning', EnsurePortalServicosEnabled::MENSAGEM);
    }

    public function test_desligado_responde_503_em_json(): void
    {
        $this->desligar();

        $this->actingAs($this->portalUser())
            ->postJson('/portal/empresas/consultar-cnpj', ['cnpj' => '00000000000191'])
            ->assertStatus(503)
            ->assertJson(['message' => EnsurePortalServicosEnabled::MENSAGEM]);
    }

    public function test_desligado_mantem_painel_notificacoes_e_conta(): void
    {
        $this->desligar();

        $user = $this->portalUser();

        $this->actingAs($user)->get('/portal/painel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('portalServicos.enabled', false)
                ->where('portalServicos.mensagem', EnsurePortalServicosEnabled::MENSAGEM));

        $this->actingAs($user)->get('/portal/notificacoes')->assertOk();
        $this->actingAs($user)->get('/portal/conta/perfil')->assertOk();
        $this->actingAs($user)->get('/portal/acessos')->assertOk();
    }

    public function test_ligado_o_servico_abre(): void
    {
        config(['viabiliza.features.portal_servicos' => true]);
        Cache::forget('viabiliza.parameters.features.portal_servicos');

        $this->actingAs($this->portalUser())
            ->get('/portal/procuracoes')
            ->assertOk();
    }

    public function test_parametro_nasce_desligado_e_o_gestor_religa_sem_deploy(): void
    {
        $this->seed(ParameterSeeder::class);

        $parametro = Parameter::query()->where('key', 'features.portal_servicos')->first();

        $this->assertNotNull($parametro);
        $this->assertSame('features', $parametro->group);
        $this->assertSame('boolean', $parametro->type);
        $this->assertSame('0', $parametro->default_value);
        $this->assertNull($parametro->value);
        $this->assertFalse(Settings::enabled('portal_servicos'));

        $parametro->update(['value' => '1']);

        $this->assertTrue(Settings::enabled('portal_servicos'));

        $this->actingAs($this->portalUser())
            ->get('/portal/empresas')
            ->assertOk();
    }
}
