<?php

namespace Tests\Feature\Portal;

use App\Enums\ParameterGovernance;
use App\Http\Middleware\EnsureConsultaViabilidadeEnabled;
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
 * Interruptor próprio da consulta prévia (features.consulta_viabilidade). Desligado,
 * só a consulta (página e os 3 endpoints) fica indisponível: os demais serviços do
 * portal e o histórico das consultas já feitas seguem funcionando.
 */
class ConsultaViabilidadeInterruptorTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config(['viabiliza.features.portal_servicos' => true]);
        Cache::forget('viabiliza.parameters.features.portal_servicos');
    }

    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    private function desligar(): void
    {
        Parameter::query()->where('key', 'features.consulta_viabilidade')->update(['value' => '0']);
        Cache::forget('viabiliza.parameters.features.consulta_viabilidade');
    }

    public function test_migration_cria_o_parametro_sem_depender_do_seed(): void
    {
        $parametro = Parameter::query()->where('key', 'features.consulta_viabilidade')->first();

        $this->assertNotNull($parametro, 'Ambiente que só roda migrate (OKD) precisa ter o parâmetro na tela.');
        $this->assertSame('features', $parametro->group);
        $this->assertSame('boolean', $parametro->type);
        $this->assertSame('1', $parametro->default_value);
        $this->assertNull($parametro->value);
        $this->assertSame(ParameterGovernance::Operational, $parametro->governance);
        $this->assertTrue(Settings::enabled('consulta_viabilidade'));
    }

    public function test_migration_nao_sobrescreve_o_valor_que_o_gestor_gravou(): void
    {
        Parameter::query()->where('key', 'features.consulta_viabilidade')->update(['value' => '0']);

        (require database_path('migrations/2026_10_17_100000_cria_parametro_consulta_viabilidade.php'))->up();

        $this->assertSame('0', Parameter::query()->where('key', 'features.consulta_viabilidade')->value('value'));
        $this->assertSame(1, Parameter::query()->where('key', 'features.consulta_viabilidade')->count());
    }

    public function test_parametro_nasce_ligado_e_sem_valor_proprio(): void
    {
        $this->seed(ParameterSeeder::class);

        $parametro = Parameter::query()->where('key', 'features.consulta_viabilidade')->first();

        $this->assertNotNull($parametro);
        $this->assertSame('features', $parametro->group);
        $this->assertSame('boolean', $parametro->type);
        $this->assertSame('1', $parametro->default_value);
        $this->assertNull($parametro->value);
        $this->assertTrue(Settings::enabled('consulta_viabilidade'));
    }

    public function test_desligado_bloqueia_a_pagina_do_cidadao_logado_com_aviso(): void
    {
        $this->desligar();

        $this->actingAs($this->portalUser())
            ->get('/portal/viabilidade')
            ->assertRedirect(route('portal.dashboard'))
            ->assertSessionHas('warning', EnsureConsultaViabilidadeEnabled::MENSAGEM);
    }

    public function test_desligado_bloqueia_a_pagina_do_visitante_com_aviso(): void
    {
        $this->desligar();

        $this->get('/portal/viabilidade')
            ->assertRedirect(route('home'))
            ->assertSessionHas('warning', EnsureConsultaViabilidadeEnabled::MENSAGEM);
    }

    public function test_desligado_bloqueia_os_tres_endpoints_com_503(): void
    {
        $this->desligar();

        foreach ([
            ['/portal/viabilidade/endereco', ['endereco' => 'Praça da Sé, Salvador', 'cnae' => '4712-1/00', 'area' => 120]],
            ['/portal/viabilidade/cnae', ['cnae' => '4712-1/00']],
            ['/portal/viabilidade/inscricao', ['inscricao' => '123456', 'cnae' => '4712-1/00', 'area' => 120]],
        ] as [$url, $corpo]) {
            $this->postJson($url, $corpo)
                ->assertStatus(503)
                ->assertJson(['message' => EnsureConsultaViabilidadeEnabled::MENSAGEM]);
        }
    }

    public function test_desligado_nao_afeta_os_outros_servicos_nem_o_historico(): void
    {
        $this->desligar();
        $user = $this->portalUser();

        foreach (['/portal/empresas', '/portal/renovacao', '/portal/procuracoes', '/portal/viabilidade/historico', '/portal/painel'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_ligado_com_o_interruptor_geral_desligado_continua_bloqueado(): void
    {
        config(['viabiliza.features.portal_servicos' => false]);
        Cache::forget('viabiliza.parameters.features.portal_servicos');

        $this->get('/portal/viabilidade')->assertRedirect(route('home'));
        $this->postJson('/portal/viabilidade/cnae', ['cnae' => '4712-1/00'])->assertStatus(503);
    }

    public function test_gestor_liga_e_desliga_so_a_consulta_pelo_parametro(): void
    {
        $this->seed(ParameterSeeder::class);
        Parameter::query()->where('key', 'features.portal_servicos')->firstOrFail()->update(['value' => '1']);
        $parametro = Parameter::query()->where('key', 'features.consulta_viabilidade')->firstOrFail();

        $this->get('/portal/viabilidade')->assertOk();
        $this->postJson('/portal/viabilidade/cnae', ['cnae' => '4712-1/00'])->assertOk();

        $parametro->update(['value' => '0']);

        $this->get('/portal/viabilidade')->assertRedirect(route('home'));
        $this->postJson('/portal/viabilidade/cnae', ['cnae' => '4712-1/00'])->assertStatus(503);
        $this->actingAs($this->portalUser())->get('/portal/empresas')->assertOk();

        $parametro->update(['value' => '1']);

        $this->get('/portal/viabilidade')->assertOk();
        $this->postJson('/portal/viabilidade/cnae', ['cnae' => '4712-1/00'])->assertOk();
    }

    public function test_estado_do_interruptor_chega_ao_front_como_prop_compartilhada(): void
    {
        $user = $this->portalUser();

        $this->actingAs($user)->get('/portal/painel')->assertInertia(fn (Assert $page) => $page
            ->where('consultaViabilidade.enabled', true)
            ->where('consultaViabilidade.mensagem', EnsureConsultaViabilidadeEnabled::MENSAGEM));

        $this->desligar();

        $this->actingAs($user)->get('/portal/painel')->assertInertia(fn (Assert $page) => $page
            ->where('consultaViabilidade.enabled', false));
    }
}
