<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Lisa, 22/10 (item 2): o campo Empresa mostra a razão social, nunca o nome do
 * solicitante; o solicitante fica no campo Requerente.
 */
class EmpresaERequerenteTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function processo(Company $empresa, ?string $solicitante): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create(['company_id' => $empresa->id]);
        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::EmAnalise,
            'solicitante_nome' => $solicitante,
        ])->save();

        return $processo;
    }

    public function test_empresa_e_a_razao_social_e_nao_o_nome_fantasia(): void
    {
        $empresa = Company::factory()->create(['legal_name' => 'Padaria Pão Quente Ltda', 'trade_name' => 'Pão Quente']);
        $processo = $this->processo($empresa, 'Maria Souza');

        $this->assertSame('Padaria Pão Quente Ltda', $processo->nomeDaEmpresa());
        $this->assertSame('Maria Souza', $processo->nomeDoRequerente());
    }

    public function test_empresa_a_constituir_com_o_nome_do_solicitante_nao_repete_o_solicitante(): void
    {
        $empresa = Company::factory()->create(['cnpj' => '12345678901', 'legal_name' => 'MARIA SOUZA', 'trade_name' => null]);
        $processo = $this->processo($empresa, 'Maria Souza');

        $this->assertSame('Empresa a constituir', $processo->nomeDaEmpresa());
    }

    public function test_sem_solicitante_o_requerente_e_o_usuario_que_pediu(): void
    {
        $empresa = Company::factory()->create();
        $processo = $this->processo($empresa, null);

        $this->assertSame($processo->requester->name, $processo->nomeDoRequerente());
    }

    public function test_ficha_traz_empresa_e_requerente(): void
    {
        $empresa = Company::factory()->create(['legal_name' => 'Oficina Boa Ltda', 'trade_name' => 'Oficina Boa']);
        $processo = $this->processo($empresa, 'João Lima');
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $processo->forceFill(['assigned_user_id' => $analista->id])->save();

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('processo.empresa', 'Oficina Boa Ltda')
                ->where('processo.requerente', 'João Lima'));
    }
}
