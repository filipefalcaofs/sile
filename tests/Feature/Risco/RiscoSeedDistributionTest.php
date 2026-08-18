<?php

namespace Tests\Feature\Risco;

use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use Database\Seeders\RiscoMunicipalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regressão de domínio da carga oficial do risco MUNICIPAL (Decreto nº
 * 32.636/2020): a distribuição por nível (767 Baixo A + 328 Baixo B + 236 Alto
 * = 1.331) e a unicidade por CNAE na versão vigente são o critério de aceite do
 * dado. Se o CSV oficial mudar (nova redação do Decreto), estes testes falham e
 * forçam reconferência antes de qualquer deploy — o número é a âncora, não o
 * código. Independente do DatabaseSeederTest: seeda só a dimensão municipal.
 */
class RiscoSeedDistributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RiscoMunicipalSeeder::class);
    }

    public function test_distribuicao_oficial_do_decreto(): void
    {
        $version = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first();
        $this->assertNotNull($version, 'A versão vigente do risco municipal deveria existir após o seed.');

        $porNivel = fn (RiscoMunicipal $nivel): int => RiskClassification::query()
            ->where('rule_version_id', $version->getKey())
            ->where('risco_municipal', $nivel->value)
            ->count();

        $this->assertSame(767, $porNivel(RiscoMunicipal::BaixoA), 'Baixo Risco A diverge do Decreto 32.636/2020.');
        $this->assertSame(328, $porNivel(RiscoMunicipal::BaixoB), 'Baixo Risco B diverge do Decreto 32.636/2020.');
        $this->assertSame(236, $porNivel(RiscoMunicipal::Alto), 'Alto Risco diverge do Decreto 32.636/2020.');

        $this->assertSame(
            1331,
            RiskClassification::query()->where('rule_version_id', $version->getKey())->count(),
            'O total de classificações municipais diverge do Decreto 32.636/2020.',
        );
    }

    public function test_cobertura_e_unica_por_cnae_na_versao_vigente(): void
    {
        $version = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first();
        $this->assertNotNull($version);

        $base = RiskClassification::query()->where('rule_version_id', $version->getKey());

        $total = (clone $base)->count();
        $distintos = (clone $base)->distinct()->count('cnae_code');

        $this->assertSame(1331, $total);
        $this->assertSame(
            $total,
            $distintos,
            'Há cnae_code duplicado na versão vigente — a cobertura deve ser única por CNAE.',
        );
    }
}
