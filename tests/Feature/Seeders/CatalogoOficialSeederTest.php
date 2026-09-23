<?php

namespace Tests\Feature\Seeders;

use App\Enums\RuleDomain;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\RuleVersion;
use App\Models\TratamentoEnquadramento;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\CatalogoOficialSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CatalogoOficialSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_carga_oficial_publica_cnaes_e_planilha_sem_massa_de_demonstracao(): void
    {
        $this->seed(CatalogoOficialSeeder::class);

        $this->assertSame(1331, Cnae::query()->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoTratamento)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro10)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoMunicipal)->count());
        $this->assertSame(2850, TratamentoEnquadramento::query()->count());
        $this->assertSame(0, Company::query()->count());
        $this->assertSame(0, ViabilityRequest::query()->count());
        $this->assertNull(User::query()->where('email', 'admin@sile.dev')->first());
    }

    public function test_segunda_carga_nao_duplica_a_versao_vigente(): void
    {
        $this->seed(CatalogoOficialSeeder::class);
        $this->seed(CatalogoOficialSeeder::class);

        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoTratamento)->count());
        $this->assertSame(1331, Cnae::query()->count());
        $this->assertSame(2850, TratamentoEnquadramento::query()->count());
    }

    public function test_a_suite_nao_recebe_o_catalogo_pela_migracao(): void
    {
        $this->assertSame(0, Cnae::query()->count());
        $this->assertSame(0, RuleVersion::vigente(RuleDomain::RiscoTratamento)->count());
    }
}
