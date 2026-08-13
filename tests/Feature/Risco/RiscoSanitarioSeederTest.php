<?php

namespace Tests\Feature\Risco;

use App\Enums\RuleDomain;
use App\Models\Activity;
use App\Models\RiskClassification;
use App\Models\RiskCondicionante;
use App\Models\RuleVersion;
use App\Models\SanitaryRiskClassification;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Carga oficial da classificação de risco SANITÁRIO (HU-019/HU-047/HU-048): o
 * seeder publica uma versão vigente do domínio risco_sanitario e importa a
 * planilha unificada CNAE da VISA (285 → 261), auditando com a versão de
 * regras. A dimensão sanitária é SEPARADA da municipal — comprovado por teste
 * (tabelas e versões distintas). Contagens assertadas sobre o CSV REAL.
 */
class RiscoSanitarioSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_publica_versao_sanitaria_e_carrega_a_planilha(): void
    {
        $this->seed(RiscoSanitarioSeeder::class);

        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoSanitario)->count());

        $vigente = RuleVersion::vigente(RuleDomain::RiscoSanitario)->sole();
        $this->assertSame('visa-unificada-2026-04-30', $vigente->version);

        $this->assertSame(261, SanitaryRiskClassification::query()->count());
        $this->assertSame(67, RiskCondicionante::query()->count());

        // Toda a carga aponta para a versão vigente (dado versionado).
        $this->assertSame(
            261,
            SanitaryRiskClassification::query()->where('rule_version_id', $vigente->id)->count(),
        );

        // Golden case da VISA presente após o seed (reclassifica para alto).
        $golden = RiskCondicionante::query()->where('cnae_code', '1031700')->sole();
        $this->assertSame('alto', $golden->regra_reclassificacao['reclassifica_para']);

        // Carga auditada com a versão de regras (RN-002).
        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'risco')
                ->where('event', 'importacao-classificacao-sanitaria')
                ->where('rules_version', 'visa-unificada-2026-04-30')
                ->exists()
        );
    }

    public function test_dimensoes_municipal_e_sanitaria_sao_separadas(): void
    {
        $this->seed(RiscoMunicipalSeeder::class);
        $this->seed(RiscoSanitarioSeeder::class);

        // Uma versão vigente por domínio — dimensões independentes (RN-009).
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoMunicipal)->count());
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoSanitario)->count());

        // Tabelas distintas, com contagens próprias (municipal × sanitária).
        $this->assertSame(1331, RiskClassification::query()->count());
        $this->assertSame(261, SanitaryRiskClassification::query()->count());

        // Cada classificação aponta para a versão do seu próprio domínio.
        $municipal = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->sole();
        $sanitaria = RuleVersion::vigente(RuleDomain::RiscoSanitario)->sole();

        $this->assertNotSame($municipal->id, $sanitaria->id);
        $this->assertSame(
            1331,
            RiskClassification::query()->where('rule_version_id', $municipal->id)->count(),
        );
        $this->assertSame(
            261,
            SanitaryRiskClassification::query()->where('rule_version_id', $sanitaria->id)->count(),
        );
    }

    public function test_seeder_e_idempotente(): void
    {
        $this->seed(RiscoSanitarioSeeder::class);
        $this->seed(RiscoSanitarioSeeder::class);

        $this->assertSame(1, RuleVersion::vigente(RuleDomain::RiscoSanitario)->count());
        $this->assertSame(261, SanitaryRiskClassification::query()->count());
        $this->assertSame(67, RiskCondicionante::query()->count());
    }
}
