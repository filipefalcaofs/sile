<?php

namespace Tests\Feature\Regin;

use App\Models\Parameter;
use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReginIntegrationControllerTest extends TestCase
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

    public function test_administrador_ve_a_tela_com_homologacao_padrao(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/config-regin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/config-regin/index')
                ->where('config.em_producao', false)
                ->where('config.url_homologacao', 'http://10.57.247.9:8080/api_integracao')
                ->where('config.url_producao', 'http://regin.prefeitura.juceb.ba.gov.br:8080/api_integracao')
                ->where('config.usuario', 'sedur_integracao')
                ->where('config.senha', null)
                ->where('config.url_ativa', 'http://10.57.247.9:8080/api_integracao')
                ->where('config.tem_senha', false));
    }

    public function test_sem_permissao_o_acesso_e_negado(): void
    {
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/config-regin')
            ->assertForbidden();
    }

    public function test_grava_toggle_e_urls_e_escolhe_a_url_de_producao(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/config-regin', [
                'em_producao' => true,
                'url_homologacao' => 'http://10.57.247.9:8080/api_integracao',
                'url_producao' => 'http://regin.exemplo.ba.gov.br:8080/api_integracao',
                'usuario' => 'sedur_integracao',
                'senha' => '',
            ])
            ->assertRedirect();

        $this->assertTrue(
            (bool) Parameter::query()->where('key', 'integrations.regin.em_producao')->first()?->typedValue(),
        );
        $this->assertSame(
            'http://regin.exemplo.ba.gov.br:8080/api_integracao',
            Parameter::query()->where('key', 'integrations.regin.url_producao')->value('value'),
        );

        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/config-regin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('config.em_producao', true)
                ->where('config.url_ativa', 'http://regin.exemplo.ba.gov.br:8080/api_integracao'));
    }

    public function test_senha_em_branco_mantem_a_atual_e_nunca_reaparece(): void
    {
        Parameter::query()->where('key', 'integrations.regin.senha')->firstOrFail()
            ->update(['value' => 'senha-original-regin']);

        $this->actingAs($this->admin(), 'gestao')
            ->put('/gestao/config-regin', [
                'em_producao' => false,
                'url_homologacao' => 'http://10.57.247.9:8080/api_integracao',
                'url_producao' => 'http://regin.prefeitura.juceb.ba.gov.br:8080/api_integracao',
                'usuario' => 'sedur_integracao',
                'senha' => '',
            ])
            ->assertRedirect();

        $this->assertSame(
            'senha-original-regin',
            Parameter::query()->where('key', 'integrations.regin.senha')->first()?->value,
        );

        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/config-regin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('config.senha', null)
                ->where('config.tem_senha', true));
    }

    public function test_testar_conexao_chama_a_url_ativa_e_nao_vaza_segredo(): void
    {
        Parameter::query()->where('key', 'integrations.regin.senha')->firstOrFail()
            ->update(['value' => 'senha-secreta-regin']);

        Http::fake([
            'http://10.57.247.9:8080/api_integracao/acesso/auth' => Http::response([
                'token' => 'jwt-nao-vazar',
            ], 200),
        ]);

        $this->actingAs($this->admin(), 'gestao')
            ->post('/gestao/config-regin/testar')
            ->assertRedirect()
            ->assertSessionHas('status');

        $status = session('status');
        $this->assertIsString($status);
        $this->assertStringNotContainsString('senha-secreta-regin', $status);
        $this->assertStringNotContainsString('jwt-nao-vazar', $status);
    }
}
