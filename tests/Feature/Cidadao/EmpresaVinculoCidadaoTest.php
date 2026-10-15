<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\GovBrAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
}
