<?php

namespace Tests\Feature\Realty;

use App\Models\Parameter;
use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InscricaoImobiliariaIntegrationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ParameterSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    public function test_administrador_ve_a_tela_com_producao_padrao(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/config-inscricao-imobiliaria')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/config-inscricao-imobiliaria/index')
                ->where('config.em_producao', true)
                ->where(
                    'config.url_homologacao',
                    'https://api.sedur.salvador.ba.gov.br/k8s/hml/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
                )
                ->where(
                    'config.url_producao',
                    'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
                )
                ->where(
                    'config.url_ativa',
                    'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
                ));
    }

    public function test_sem_permissao_o_acesso_e_negado(): void
    {
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/config-inscricao-imobiliaria')
            ->assertForbidden();
    }

    public function test_grava_toggle_e_urls_e_escolhe_a_url_de_homologacao(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/config-inscricao-imobiliaria', [
                'em_producao' => false,
                'url_homologacao' => 'https://api.sedur.exemplo.ba.gov.br/k8s/hml/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
                'url_producao' => 'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
            ])
            ->assertRedirect();

        $this->assertFalse(
            (bool) Parameter::query()->where('key', 'integrations.inscricao_imobiliaria.em_producao')->first()?->typedValue(),
        );
        $this->assertSame(
            'https://api.sedur.exemplo.ba.gov.br/k8s/hml/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
            Parameter::query()->where('key', 'integrations.inscricao_imobiliaria.url_homologacao')->value('value'),
        );

        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/config-inscricao-imobiliaria')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('config.em_producao', false)
                ->where(
                    'config.url_ativa',
                    'https://api.sedur.exemplo.ba.gov.br/k8s/hml/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
                ));
    }

    public function test_testar_conexao_chama_a_url_ativa_e_nao_vaza_corpo(): void
    {
        Http::fake([
            'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-bff-portal-servicos/v1/inscricao-imobiliaria/0000000000' => Http::response([
                'CodigoRetorno' => '1',
                'MensagemRetorno' => 'segredo-nao-vazar',
            ], 200),
        ]);

        $this->actingAs($this->admin(), 'gestao')
            ->post('/gestao/config-inscricao-imobiliaria/testar')
            ->assertRedirect()
            ->assertSessionHas('status');

        $status = session('status');
        $this->assertIsString($status);
        $this->assertStringNotContainsString('segredo-nao-vazar', $status);
    }
}
