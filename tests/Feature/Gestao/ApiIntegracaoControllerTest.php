<?php

namespace Tests\Feature\Gestao;

use App\Models\Parameter;
use App\Models\User;
use App\Services\Sefaz\LegadoViabilidadeFonte;
use App\Support\Settings;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApiIntegracaoControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ParameterSeeder::class);
    }

    public function test_administrador_ve_as_abas_sem_credencial_em_claro(): void
    {
        Parameter::query()->where('key', 'integrations.govbr.client_secret')->first()
            ->update(['value' => 'segredo-govbr-que-nao-pode-aparecer']);

        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/config-apis')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/config-apis/index')
                ->where('aba', 'regin')
                ->has('abas', 9)
                ->where('abas.0.nome', 'REGIN')
                ->where('abas.1.nome', 'Inscrição imobiliária')
                ->where('abas.2.nome', 'CPF e CNPJ')
                ->where('abas.2.campos.2.valor', 'https://api.sedur.salvador.ba.gov.br/k8s/hml/ws-contato')
                ->where('abas.2.campos.3.valor', 'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-contato')
                ->where('abas.4.campos.5.valor', null)
                ->where('abas.4.campos.5.tem_valor', true))
            ->assertDontSee('segredo-govbr-que-nao-pode-aparecer');
    }

    public function test_atualiza_a_url_do_contato_sedur(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/config-apis/sedur_contato', [
                'campos' => [
                    'habilitada' => '1',
                    'em_producao' => '0',
                    'url_homologacao' => 'https://api.sedur.salvador.ba.gov.br/k8s/hml/ws-contato',
                    'url_producao' => 'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-contato',
                ],
            ])
            ->assertRedirect(route('gestao.config-apis.edit', ['aba' => 'sedur_contato']));

        $this->assertSame(
            'https://api.sedur.salvador.ba.gov.br/k8s/hml/ws-contato',
            Parameter::query()->where('key', 'integrations.sedur_contato.base_url')->first()->value,
        );
    }

    public function test_senha_em_branco_nao_apaga_a_credencial(): void
    {
        Parameter::query()->where('key', 'integrations.regin.senha')->first()
            ->update(['value' => 'senha-antiga-8']);

        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/config-apis/regin', [
                'campos' => [
                    'em_producao' => '0',
                    'url_homologacao' => 'http://10.57.247.9:8080/api_integracao',
                    'url_producao' => 'http://regin.prefeitura.juceb.ba.gov.br:8080/api_integracao',
                    'usuario' => 'sedur_integracao',
                    'senha' => '',
                    'cnpj_prefeitura' => '13927801000149',
                ],
            ])
            ->assertRedirect();

        $this->assertSame(
            'senha-antiga-8',
            Parameter::query()->where('key', 'integrations.regin.senha')->first()->value,
        );
    }

    public function test_token_do_semap_e_gravado_criptografado_e_nao_volta_na_tela(): void
    {
        $token = str_repeat('a1B2c3D4', 40);

        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/config-apis/arcgis_semap', [
                'campos' => [
                    'habilitada' => '1',
                    'query_url' => 'https://geo.salvador.ba.gov.br/arcgis/rest/services/MAPA_BASE/MAPA_BASE_SEFAZ/MapServer/18/query',
                    'token' => $token,
                ],
            ])
            ->assertRedirect(route('gestao.config-apis.edit', ['aba' => 'arcgis_semap']));

        $parameter = Parameter::query()->where('key', 'integrations.arcgis_semap.token')->first();

        $this->assertTrue($parameter->sensitive);
        $this->assertSame($token, $parameter->value);
        $this->assertNotSame($token, $parameter->getRawOriginal('value'));
        $this->assertSame($token, Settings::get('integrations.arcgis_semap.token'));

        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/config-apis?aba=arcgis_semap')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('abas.7.id', 'arcgis_semap')
                ->where('abas.7.campos.2.valor', null)
                ->where('abas.7.campos.2.tem_valor', true))
            ->assertDontSee($token);
    }

    public function test_legado_simplifica_tem_aba_propria_com_interruptor_url_e_token(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/config-apis?aba=legado_simplifica')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('aba', 'legado_simplifica')
                ->where('abas.8.id', 'legado_simplifica')
                ->where('abas.8.nome', 'Legado Simplifica')
                ->where('abas.8.campos.0.id', 'habilitada')
                ->where('abas.8.campos.0.valor', false)
                ->where('abas.8.campos.1.id', 'url')
                ->where('abas.8.campos.2.id', 'token')
                ->where('abas.8.campos.2.tipo', 'senha'));
    }

    public function test_token_do_legado_e_gravado_criptografado_e_a_fonte_passa_a_valer(): void
    {
        $token = 'lg_'.str_repeat('a1B2c3D4', 8);

        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/config-apis/legado_simplifica', [
                'campos' => ['habilitada' => '1', 'url' => 'http://simplifica-legado.pms.ba.gov.br', 'token' => $token],
            ])
            ->assertRedirect(route('gestao.config-apis.edit', ['aba' => 'legado_simplifica']));

        $parameter = Parameter::query()->where('key', 'integrations.legado_simplifica.token')->first();

        $this->assertTrue($parameter->sensitive);
        $this->assertSame($token, $parameter->value);
        $this->assertNotSame($token, $parameter->getRawOriginal('value'));
        $this->assertTrue(app(LegadoViabilidadeFonte::class)->disponivel());

        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/config-apis?aba=legado_simplifica')
            ->assertInertia(fn (Assert $page) => $page
                ->where('abas.8.campos.2.valor', null)
                ->where('abas.8.campos.2.tem_valor', true))
            ->assertDontSee($token);
    }

    public function test_fonte_do_legado_fica_indisponivel_com_o_interruptor_desligado(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/config-apis/legado_simplifica', [
                'campos' => ['habilitada' => '0', 'url' => 'http://simplifica-legado.pms.ba.gov.br', 'token' => 'lg_abc'],
            ]);

        $this->assertFalse(app(LegadoViabilidadeFonte::class)->disponivel());
    }

    public function test_url_do_legado_invalida_e_rejeitada(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/config-apis/legado_simplifica', ['campos' => ['habilitada' => '1', 'url' => 'nao-e-url']])
            ->assertSessionHasErrors('campos.url');
    }

    public function test_teste_de_conexao_do_legado(): void
    {
        $this->actingAs($this->admin(), 'gestao')->put('/gestao/config-apis/legado_simplifica', [
            'campos' => ['habilitada' => '1', 'url' => 'http://legado.test', 'token' => 'lg_teste'],
        ]);

        $status = 200;
        Http::fake(function () use (&$status) {
            if ($status === 0) {
                throw new ConnectionException('sem rota');
            }

            return Http::response($status === 200 ? ['data' => []] : ['mensagem' => 'x'], $status);
        });

        $this->actingAs($this->admin(), 'gestao')
            ->post('/gestao/config-legado-simplifica/testar')
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();
        Http::assertSent(fn ($r): bool => $r->hasHeader('Authorization', 'Bearer lg_teste'));

        foreach ([401 => 'token', 403 => 'escopo', 500 => 'falha', 0 => 'indisponível'] as $codigo => $trecho) {
            $status = $codigo;
            $this->actingAs($this->admin(), 'gestao')
                ->post('/gestao/config-legado-simplifica/testar')
                ->assertSessionHasErrors('legado_connection');
            $this->assertStringContainsStringIgnoringCase($trecho, session('errors')->first('legado_connection'), "caso {$codigo}");
        }
    }

    public function test_teste_de_conexao_do_legado_sem_url_ou_token_orienta_a_configurar(): void
    {
        Http::fake();

        $this->actingAs($this->admin(), 'gestao')
            ->post('/gestao/config-legado-simplifica/testar')
            ->assertSessionHasErrors('legado_connection');

        Http::assertNothingSent();
    }

    public function test_url_invalida_e_rejeitada(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->from('/gestao/config-apis?aba=sedur_contato')
            ->put('/gestao/config-apis/sedur_contato', [
                'campos' => [
                    'habilitada' => '1',
                    'em_producao' => '0',
                    'url_homologacao' => 'nao-e-url',
                    'url_producao' => 'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-contato',
                ],
            ])
            ->assertRedirect('/gestao/config-apis?aba=sedur_contato')
            ->assertSessionHasErrors('campos.url_homologacao');
    }

    public function test_analista_nao_acessa(): void
    {
        $this->actingAs(User::factory()->analista()->withAcceptedLgpdTerm()->create(), 'gestao')
            ->get('/gestao/config-apis')
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }
}
