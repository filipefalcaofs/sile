<?php

namespace Tests\Feature\Renovacao;

use App\Enums\CompanyLinkRole;
use App\Enums\TvlKind;
use App\Enums\ViabilityRequestStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RenovacaoConsultaCnpjTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config(['sile.integrations.sedur_contato.base_url' => 'https://sedur.test/k8s/prd/ws-contato']);
    }

    public function test_consulta_cnpj_devolve_cadastro_da_sedur_e_a_viabilidade_local(): void
    {
        $cidadao = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
        $empresa = Company::factory()->create();
        $empresa->links()->create([
            'user_id' => $cidadao->id,
            'role' => CompanyLinkRole::Responsavel,
            'started_at' => now(),
        ]);
        $origem = ViabilityRequest::factory()->create(['company_id' => $empresa->id]);
        $origem->forceFill(['status' => ViabilityRequestStatus::Deferida])->save();
        ViabilityDecision::factory()->create([
            'viability_request_id' => $origem->id,
            'tvl_kind' => TvlKind::Provisoria,
            'tvl_product_number' => 'TVL-'.$origem->id,
            'tvl_valid_until' => '2027-09-28',
        ]);

        Http::fake([
            '*/pessoajuridica/cnpj/'.$empresa->cnpj => Http::response([
                'razaoSocial' => 'EMPRESA CONSULTADA LTDA',
                'nomeFantasia' => 'CONSULTADA',
                'telefone' => '7133334444',
                'endereco' => ['logradouro' => 'Rua da Consulta', 'numero' => '10'],
            ]),
        ]);

        $this->actingAs($cidadao)
            ->postJson('/portal/renovacao/consultar-cnpj', ['cnpj' => $empresa->cnpj])
            ->assertOk()
            ->assertJsonPath('razao_social', 'EMPRESA CONSULTADA LTDA')
            ->assertJsonPath('viabilidades.0.id', $origem->id)
            ->assertJsonPath('viabilidades.0.elegivel', true);
    }

    public function test_cnpj_nao_encontrado_na_sedur(): void
    {
        $cidadao = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
        $empresa = Company::factory()->create();

        Http::fake([
            '*/pessoajuridica/cnpj/*' => Http::response([], 404),
        ]);

        $this->actingAs($cidadao)
            ->postJson('/portal/renovacao/consultar-cnpj', ['cnpj' => $empresa->cnpj])
            ->assertNotFound()
            ->assertJsonPath('message', 'CNPJ não encontrado na base da SEDUR.');
    }
}
