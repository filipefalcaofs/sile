<?php

namespace Tests\Feature\Gestao;

use App\Models\Parameter;
use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
                ->has('abas', 7)
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
