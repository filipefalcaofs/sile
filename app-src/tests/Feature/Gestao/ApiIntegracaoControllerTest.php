<?php

namespace Tests\Feature\Gestao;

use App\Models\Parameter;
use App\Models\User;
use App\Services\Sefaz\LegadoViabilidadeFonte;
use App\Support\Settings;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
                ->has('abas', 11)
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
                ->where('abas.8.id', 'arcgis_semap')
                ->where('abas.8.campos.2.valor', null)
                ->where('abas.8.campos.2.tem_valor', true))
            ->assertDontSee($token);
    }

    public function test_aba_edificacoes_tem_interruptores_urls_e_token_sensivel(): void
    {
        $token = str_repeat('x9Y8', 30);

        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/config-apis/edificacoes', [
                'campos' => [
                    'overture' => '1',
                    'microsoft' => '0',
                    'ia' => '1',
                    'importacao_agendada' => '0',
                    'ia_url' => 'http://footprint-ia:8080',
                    'ortofoto_2024_url' => 'https://geo.salvador.ba.gov.br/imageserver/rest/services/Ortofotos/2024/ImageServer',
                    'ortofoto_2016_url' => 'https://mapeamento.salvador.ba.gov.br/wms',
                    'ortofoto_token' => $token,
                ],
            ])
            ->assertRedirect(route('gestao.config-apis.edit', ['aba' => 'edificacoes']));

        $this->assertTrue(Settings::enabled('edificacoes_overture'));
        $this->assertFalse(Settings::enabled('edificacoes_microsoft'));
        $this->assertSame('http://footprint-ia:8080', Settings::get('integrations.edificacoes.ia_url'));
        $parameter = Parameter::query()->where('key', 'integrations.edificacoes.ortofoto_token')->first();
        $this->assertTrue($parameter->sensitive);
        $this->assertNotSame($token, $parameter->getRawOriginal('value'));

        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/config-apis?aba=edificacoes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('abas.9.id', 'edificacoes')
                ->where('abas.9.nome', 'Edificações complementares')
                ->has('abas.9.campos', 8)
                ->where('abas.9.campos.7.valor', null)
                ->where('abas.9.campos.7.tem_valor', true))
            ->assertDontSee($token);
    }

    public function test_legado_simplifica_tem_uma_aba_so_com_o_interruptor_da_comparacao(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/config-apis?aba=legado')
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $abas = collect($page->toArray()['props']['abas']);

                $this->assertCount(1, $abas->filter(fn (array $aba): bool => str_contains(strtolower($aba['nome']), 'simplifica')));
                $this->assertNull($abas->firstWhere('id', 'legado_simplifica'));

                $campo = collect($abas->firstWhere('id', 'legado')['campos'])->firstWhere('id', 'comparacao');
                $this->assertSame('interruptor', $campo['tipo']);
                $this->assertFalse($campo['valor']);
            });
    }

    public function test_comparacao_do_legado_usa_a_conexao_da_aba_unica(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/config-apis/legado', ['campos' => [
                'em_producao' => '0',
                'url_homologacao' => 'https://legado-hml.test/v1',
                'url_producao' => '',
                'token' => 'lg_token_da_aba_unica',
                'usuario' => '',
                'senha_acesso' => '',
                'comparacao' => '1',
            ]])
            ->assertRedirect(route('gestao.config-apis.edit', ['aba' => 'legado']));

        $this->assertTrue(app(LegadoViabilidadeFonte::class)->disponivel());

        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/config-apis/legado', ['campos' => [
                'em_producao' => '0',
                'url_homologacao' => 'https://legado-hml.test/v1',
                'url_producao' => '',
                'token' => '',
                'usuario' => '',
                'senha_acesso' => '',
                'comparacao' => '0',
            ]]);

        $this->assertFalse(app(LegadoViabilidadeFonte::class)->disponivel());
    }

    public function test_comparacao_fica_indisponivel_sem_conexao_configurada(): void
    {
        Parameter::query()->where('key', 'features.legado_simplifica')->update(['value' => '1']);
        Cache::flush();

        $this->assertFalse(app(LegadoViabilidadeFonte::class)->disponivel());
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
