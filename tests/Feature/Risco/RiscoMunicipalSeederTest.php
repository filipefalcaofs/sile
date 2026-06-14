<?php

namespace Tests\Feature\Risco;

use App\Enums\RuleDomain;
use App\Models\Activity;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use Database\Seeders\RiscoMunicipalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Carga oficial da classificação de risco municipal (HU-020/HU-047): o seeder
 * publica uma versão vigente do domínio risco_municipal e importa o Decreto
 * nº 32.636/2020 (767/328/236 = 1.331), auditando com a versão de regras. A
 * contagem é assertada sobre o CSV REAL commitado, sem fixture sintético.
 */
class RiscoMunicipalSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_publica_versao_vigente_e_carrega_o_decreto(): void
    {
        $this->seed(RiscoMunicipalSeeder::class);

        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoMunicipal)->count());

        $vigente = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->sole();
        $this->assertSame('decreto-32636-2020', $vigente->version);

        $this->assertSame(1331, RiskClassification::query()->count());
        $this->assertSame(767, RiskClassification::query()->where('risco_municipal', 'baixo_a')->count());
        $this->assertSame(328, RiskClassification::query()->where('risco_municipal', 'baixo_b')->count());
        $this->assertSame(236, RiskClassification::query()->where('risco_municipal', 'alto')->count());

        // Toda a carga aponta para a versão vigente (dado versionado).
        $this->assertSame(
            1331,
            RiskClassification::query()->where('rule_version_id', $vigente->id)->count(),
        );

        // Carga auditada com a versão de regras (RN-002).
        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'risco')
                ->where('event', 'importacao-classificacao-municipal')
                ->where('rules_version', 'decreto-32636-2020')
                ->exists()
        );
    }

    public function test_seeder_e_idempotente(): void
    {
        $this->seed(RiscoMunicipalSeeder::class);
        $this->seed(RiscoMunicipalSeeder::class);

        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoMunicipal)->count());
        $this->assertSame(1331, RiskClassification::query()->count());
    }
}
