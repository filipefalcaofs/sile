<?php

namespace Tests\Feature\Louos;

use App\Enums\RuleDomain;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\RuleVersion;
use App\Models\TratamentoEnquadramento;
use Database\Seeders\LouosQuadro10Seeder;
use Database\Seeders\LouosQuadro11Seeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * Regressão de domínio da carga dos Quadros da LOUOS (Lei nº 9.148/2016): as
 * contagens do Quadro 7 (1.971 faixas em 1.331 CNAEs da planilha 20.08.26) e
 * dos Quadros 10 (1.323 células em 21 zonas) e 11A (525 linhas em 7 vias) são as âncoras do dado. Se um CSV mudar, estes testes
 * falham e forçam reconferência antes de qualquer deploy — o número é a âncora,
 * não o código. Espelha RiscoSeedDistributionTest; seeda só os Quadros.
 */
class LouosSeedDistributionTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedTratamentoPlanilha();

        $this->seed([
            LouosQuadro10Seeder::class,
            LouosQuadro11Seeder::class,
        ]);
    }

    public function test_planilha_tem_versao_vigente_unica_e_enquadramentos(): void
    {
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoTratamento)->count());

        $version = RuleVersion::vigente(RuleDomain::RiscoTratamento)->firstOrFail();

        $this->assertSame(
            2850,
            TratamentoEnquadramento::query()->where('rule_version_id', $version->getKey())->count(),
            'O total de linhas da planilha 20.08.26 divergiu da carga oficial.',
        );
        $this->assertSame(
            1332,
            TratamentoEnquadramento::query()->where('rule_version_id', $version->getKey())->distinct()->count('cnae'),
            'O total de CNAEs distintos da planilha 20.08.26 divergiu da carga oficial.',
        );
    }

    public function test_quadro10_e_quadro11a_tem_versao_vigente(): void
    {
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro10)->count());
        $this->assertDatabaseMissing('rule_versions', ['domain' => 'louos_quadro11']);
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro11a)->count());

        $version10 = RuleVersion::vigente(RuleDomain::LouosQuadro10)->firstOrFail();
        $this->assertSame(
            1323,
            LouosQuadro10Permissao::query()->where('rule_version_id', $version10->getKey())->count(),
            'O total de células do Quadro 10 divergiu da matriz oficial da Lei 9.148/2016.',
        );
        $this->assertSame(
            21,
            LouosQuadro10Permissao::query()->where('rule_version_id', $version10->getKey())->distinct()->count('zona'),
        );

        $version11a = RuleVersion::vigente(RuleDomain::LouosQuadro11a)->firstOrFail();

        $this->assertSame(
            525,
            LouosQuadro11CondicaoVia::query()->where('rule_version_id', $version11a->getKey())->count(),
            'O total de linhas do Quadro 11A divergiu da matriz oficial da Lei 9.148/2016.',
        );
        $this->assertSame(
            7,
            LouosQuadro11CondicaoVia::query()->where('rule_version_id', $version11a->getKey())->distinct()->count('classe_via'),
        );
    }
}
