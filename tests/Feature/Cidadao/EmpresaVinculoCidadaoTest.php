<?php

namespace Tests\Feature\Cidadao;

use App\Http\Controllers\Portal\SolicitacaoController;
use App\Models\Cidadao;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\GovBrAccount;
use App\Models\ViabilityServiceType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class EmpresaVinculoCidadaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_vinculo_da_empresa_pertence_a_um_cidadao(): void
    {
        $cidadao = Cidadao::factory()->create();
        $empresa = Company::factory()->create();

        $vinculo = CompanyUser::factory()->for($empresa)->for($cidadao)->create();

        $this->assertSame($cidadao->id, $vinculo->cidadao_id);
        $this->assertTrue($vinculo->cidadao->is($cidadao));
        $this->assertSame(1, Company::countForCidadao($cidadao));
    }

    public function test_gov_br_pertence_a_um_cidadao(): void
    {
        $cidadao = Cidadao::factory()->create();

        $conta = GovBrAccount::factory()->create(['cidadao_id' => $cidadao->id]);

        $this->assertTrue($conta->cidadao->is($cidadao));
        $this->assertTrue($cidadao->govBrAccount->is($conta));
    }

    public function test_chaves_estrangeiras_apontam_para_cidadaos(): void
    {
        foreach (['company_user', 'gov_br_accounts'] as $tabela) {
            $fks = collect(Schema::getForeignKeys($tabela))->firstWhere('columns', ['cidadao_id']);

            $this->assertNotNull($fks, "{$tabela}.cidadao_id sem FK");
            $this->assertSame('cidadaos', $fks['foreign_table']);
            $this->assertFalse(Schema::hasColumn($tabela, 'user_id'));
        }
    }

    public function test_opcoes_de_empresa_da_solicitacao_listam_so_vinculo_ativo_do_cidadao(): void
    {
        $cidadao = Cidadao::factory()->create();
        $ativa = Company::factory()->create();
        $encerrada = Company::factory()->create();
        CompanyUser::factory()->for($ativa)->for($cidadao)->create();
        CompanyUser::factory()->ended()->for($encerrada)->for($cidadao)->create();

        $metodo = new ReflectionMethod(SolicitacaoController::class, 'companyOptions');
        $opcoes = $metodo->invoke(app(SolicitacaoController::class), $cidadao);

        $this->assertSame([$ativa->id], array_column($opcoes, 'value'));
    }

    public function test_validacao_do_vinculo_ativo_na_solicitacao_usa_o_cidadao(): void
    {
        $cidadao = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $semVinculo = Company::factory()->create();
        $servico = ViabilityServiceType::factory()->create();

        $this->actingAs($cidadao)
            ->post('/portal/solicitacoes', ['service_type_id' => $servico->id, 'company_id' => $semVinculo->id])
            ->assertSessionHasErrors(['company_id' => 'Você não possui vínculo ativo com esta empresa.']);

        $comVinculo = Company::factory()->create();
        CompanyUser::factory()->for($comVinculo)->for($cidadao)->create();

        $this->actingAs($cidadao)
            ->post('/portal/solicitacoes', ['service_type_id' => $servico->id, 'company_id' => $comVinculo->id])
            ->assertSessionDoesntHaveErrors('company_id');
    }
}
