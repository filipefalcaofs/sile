<?php

namespace Tests\Feature\Solicitacao;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pré-consulta de CPF no atendimento presencial: o operador confere o nome na
 * SEDUR antes de iniciar o vínculo. O início continua exigindo conta local.
 */
class ConsultarCpfAtendimentoTest extends TestCase
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

        $this->actingAs($this->atendente(), 'gestao')
            ->postJson(route('gestao.atendimento.consultar-cpf'), ['cpf' => $cidadao->cpf])
            ->assertOk()
            ->assertJsonPath('nome', 'Maria Silva')
            ->assertJsonPath('cadastrado', true)
            ->assertJsonMissingPath('email')
            ->assertJsonMissingPath('telefone');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'atendimento',
            'event' => 'consulta-cpf',
            'result' => 'sucesso',
            'personal_data' => true,
        ]);
    }

    public function test_cpf_inexistente_na_sedur_nao_inventa_nome(): void
    {
        Http::fake([
            '*/pessoafisica/cpf/*' => Http::response([], 404),
        ]);

        $cidadao = User::factory()->cidadao()->create();

        $this->actingAs($this->atendente(), 'gestao')
            ->postJson(route('gestao.atendimento.consultar-cpf'), ['cpf' => $cidadao->cpf])
            ->assertNotFound()
            ->assertJsonPath('message', 'CPF não encontrado na base da SEDUR.');
    }

    public function test_exige_permissao_atendimento_presencial(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->postJson(route('gestao.atendimento.consultar-cpf'), ['cpf' => '52998224725'])
            ->assertForbidden();
    }

    private function atendente(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }
}
