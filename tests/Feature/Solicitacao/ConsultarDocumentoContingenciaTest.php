<?php

namespace Tests\Feature\Solicitacao;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * CPF e CNPJ da contingência consultam o ws-contato da SEDUR e devolvem só o
 * nome, para o operador conferir. O protocolo segue exigindo cadastro local.
 */
class ConsultarDocumentoContingenciaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config(['sile.integrations.sedur_contato.base_url' => 'https://sedur.test/k8s/prd/ws-contato']);
    }

    public function test_consulta_cpf_devolve_o_nome_e_se_ja_esta_cadastrado(): void
    {
        $cidadao = User::factory()->cidadao()->create();

        Http::fake([
            'https://sedur.test/k8s/prd/ws-contato/pessoafisica/cpf/'.$cidadao->cpf => Http::response([
                'nome' => 'Maria Silva',
                'email' => 'maria@example.com',
                'telefone' => '71999999999',
            ]),
        ]);

        $this->actingAs($this->operador(), 'gestao')
            ->postJson(route('gestao.contingencia.consultar-cpf'), ['cpf' => $cidadao->cpf])
            ->assertOk()
            ->assertJsonPath('nome', 'Maria Silva')
            ->assertJsonPath('cadastrado', true)
            ->assertJsonMissingPath('email')
            ->assertJsonMissingPath('telefone');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'contingencia',
            'event' => 'consulta-cpf',
            'result' => 'sucesso',
            'personal_data' => true,
        ]);
    }

    public function test_consulta_cnpj_devolve_a_razao_social(): void
    {
        $empresa = Company::factory()->create();

        Http::fake([
            'https://sedur.test/k8s/prd/ws-contato/pessoajuridica/cnpj/'.$empresa->cnpj => Http::response([
                'cnpj' => $empresa->cnpj,
                'razaoSocial' => 'PADARIA DO BAIRRO LTDA',
                'nomeFantasia' => 'Padaria do Bairro',
                'telefone' => '7132000000',
            ]),
        ]);

        $this->actingAs($this->operador(), 'gestao')
            ->postJson(route('gestao.contingencia.consultar-cnpj'), ['cnpj' => $empresa->cnpj])
            ->assertOk()
            ->assertJsonPath('razao_social', 'PADARIA DO BAIRRO LTDA')
            ->assertJsonPath('nome_fantasia', 'Padaria do Bairro')
            ->assertJsonPath('cadastrado', true)
            ->assertJsonMissingPath('telefone');
    }

    public function test_cpf_inexistente_na_sedur_nao_inventa_nome(): void
    {
        Http::fake([
            '*/pessoafisica/cpf/*' => Http::response([], 404),
        ]);

        $cidadao = User::factory()->cidadao()->create();

        $this->actingAs($this->operador(), 'gestao')
            ->postJson(route('gestao.contingencia.consultar-cpf'), ['cpf' => $cidadao->cpf])
            ->assertNotFound()
            ->assertJsonPath('message', 'CPF não encontrado na base da SEDUR.');
    }

    public function test_exige_permissao_de_contingencia(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->postJson(route('gestao.contingencia.consultar-cpf'), ['cpf' => '52998224725'])
            ->assertForbidden();
    }

    private function operador(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }
}
