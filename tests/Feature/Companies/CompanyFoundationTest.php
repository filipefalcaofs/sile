<?php

namespace Tests\Feature\Companies;

use App\Enums\CompanyLinkRole;
use App\Enums\CompanySource;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Rules\ValidCnpj;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CompanyFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_cria_empresa_com_cnpj_valido(): void
    {
        $company = Company::factory()->create();

        $this->assertTrue(
            Validator::make(['cnpj' => $company->cnpj], ['cnpj' => [new ValidCnpj]])->passes(),
            'CNPJ gerado pela factory deveria passar na ValidCnpj',
        );
        $this->assertSame(CompanySource::Manual, $company->source);
        $this->assertNotEmpty($company->legal_name);
    }

    public function test_cnpj_duplicado_viola_unique_no_banco(): void
    {
        Company::factory()->create(['cnpj' => '00000000000191']);

        $this->expectException(QueryException::class);

        Company::factory()->create(['cnpj' => '00000000000191']);
    }

    public function test_vinculo_e_cnaes_relacionam(): void
    {
        $company = Company::factory()->create(['cnpj' => '00000000000191']);
        CompanyUser::factory()->responsavel()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $primary = Cnae::factory()->create();
        $secondary = Cnae::factory()->create();
        $company->cnaes()->attach($primary->id, ['is_primary' => true]);
        $company->cnaes()->attach($secondary->id, ['is_primary' => false]);

        $this->assertSame(1, $company->links()->count());
        $this->assertSame(2, $company->cnaes()->count());
        $this->assertSame($primary->id, $company->primaryCnae()->first()->id);
        $this->assertSame(1, $company->activeLinks()->count());
        $this->assertSame('00.000.000/0001-91', $company->formatted_cnpj);
    }

    public function test_banco_impede_excluir_cnae_vinculado(): void
    {
        $company = Company::factory()->create();
        $cnae = Cnae::factory()->create();
        $company->cnaes()->attach($cnae->id, ['is_primary' => true]);

        $this->expectException(QueryException::class);

        $cnae->delete();
    }

    public function test_state_from_redesim_marca_origem(): void
    {
        $company = Company::factory()->fromRedesim()->create();

        $this->assertSame(CompanySource::Redesim, $company->source);
        $this->assertNotNull($company->redesim_protocol);
        $this->assertNotNull($company->redesim_synced_at);
    }

    public function test_state_ended_encerra_vinculo(): void
    {
        $link = CompanyUser::factory()->ended()->create([
            'company_id' => Company::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertNotNull($link->ended_at);
        $this->assertSame(CompanyLinkRole::Responsavel, $link->role);
    }
}
