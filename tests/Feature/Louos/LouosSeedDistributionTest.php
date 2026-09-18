<?php

namespace Tests\Feature\Louos;

use App\Enums\RuleDomain;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\LouosQuadro7Faixa;
use App\Models\RuleVersion;
use Database\Seeders\LouosQuadro10Seeder;
use Database\Seeders\LouosQuadro11Seeder;
use Database\Seeders\LouosQuadro7Seeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            LouosQuadro7Seeder::class,
            LouosQuadro10Seeder::class,
            LouosQuadro11Seeder::class,
        ]);
    }

    public function test_quadro7_tem_versao_vigente_unica_e_faixas(): void
    {
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::LouosQuadro7)->count());

        $version = RuleVersion::vigente(RuleDomain::LouosQuadro7)->firstOrFail();

        // Âncora da ponte operacional CNAE→uso (planilha 20.08.26).
        $this->assertSame(
            1971,
            LouosQuadro7Faixa::query()->where('rule_version_id', $version->getKey())->count(),
            'O total de faixas do Quadro 7 divergiu do CSV operacional 20.08.26.',
        );
        $this->assertSame(
            1331,
            LouosQuadro7Faixa::query()->where('rule_version_id', $version->getKey())->distinct()->count('cnae_code'),
            'O total de CNAEs distintos do Quadro 7 divergiu do catálogo CNAE 2.3.',
        );
    }

    public function test_quadro7_nao_tem_faixas_sobrepostas_por_cnae(): void
    {
        $version = RuleVersion::vigente(RuleDomain::LouosQuadro7)->firstOrFail();

        $faixasPorCnae = LouosQuadro7Faixa::query()
            ->where('rule_version_id', $version->getKey())
            ->get()
            ->groupBy('cnae_code');

        $this->assertNotEmpty($faixasPorCnae);

        foreach ($faixasPorCnae as $cnae => $faixas) {
            $ordenadas = $faixas->sortBy(fn (LouosQuadro7Faixa $faixa): float => (float) $faixa->area_min)->values();

            for ($i = 1; $i < $ordenadas->count(); $i++) {
                $anterior = $ordenadas[$i - 1];
                $atual = $ordenadas[$i];

                $this->assertNotNull(
                    $anterior->area_max,
                    "CNAE {$cnae}: faixa sem limite superior só pode ser a última.",
                );
                $this->assertGreaterThanOrEqual(
                    (float) $anterior->area_max,
                    (float) $atual->area_min,
                    "CNAE {$cnae}: a faixa a partir de {$atual->area_min} invade o fim {$anterior->area_max} da anterior.",
                );
            }
        }
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
