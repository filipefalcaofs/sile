<?php

namespace Tests\Feature\Louos;

use App\Enums\RuleDomain;
use App\Enums\TerritorialRuleEffect;
use App\Enums\TerritorialRuleType;
use App\Models\RuleVersion;
use App\Models\TerritorialRule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Esquema do dado versionado das regras territoriais (RT-01 a RT-05): a regra
 * referencia uma rule_version do domínio `territorial` (mesmo cabeçalho dos
 * quadros LOUOS) e tem filhos tipados — CNAEs (RT-01) e endereços (allow-list
 * de Itaigara e allow-lists de via do Quadro 10).
 */
class TerritorialRuleSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dominio_territorial_existe_e_e_sensivel(): void
    {
        $this->assertSame('territorial', RuleDomain::Territorial->value);
        $this->assertTrue(RuleDomain::Territorial->isSensitive());
    }

    public function test_regra_pertence_a_versao_e_tem_filhos_tipados(): void
    {
        $versao = RuleVersion::factory()->create(['domain' => RuleDomain::Territorial]);

        $regra = TerritorialRule::create([
            'rule_version_id' => $versao->getKey(),
            'codigo' => 'RT-01',
            'titulo' => 'Logradouro 212 — Avenida Lafayette Coutinho',
            'tipo' => TerritorialRuleType::LogradouroCnae,
            'efeito' => TerritorialRuleEffect::Indeferir,
            'cod_log' => '212',
            'logradouro_nome' => 'Avenida Lafayette Coutinho',
            'base_legal' => 'Art. 172-A da Lei Municipal nº 9.148/2016, redação da Lei Municipal nº 9.879/2025',
        ]);

        $regra->cnaes()->create(['cnae' => '4520006']);
        $regra->cnaes()->create(['cnae' => '4723700']);
        $regra->addresses()->create([
            'rotulo' => 'Shopping Paseo',
            'cod_log' => '518',
            'numero_porta' => '135',
        ]);

        $regra->refresh();

        $this->assertSame($versao->getKey(), $regra->ruleVersion->getKey());
        $this->assertSame(['4520006', '4723700'], $regra->cnaes()->orderBy('cnae')->pluck('cnae')->all());
        $this->assertSame('Shopping Paseo', $regra->addresses()->first()->rotulo);
        $this->assertSame(TerritorialRuleType::LogradouroCnae, $regra->tipo);
        $this->assertSame(TerritorialRuleEffect::Indeferir, $regra->efeito);
        $this->assertTrue($regra->ativo);
    }

    public function test_cnae_nao_se_repete_na_mesma_regra(): void
    {
        $versao = RuleVersion::factory()->create(['domain' => RuleDomain::Territorial]);

        $regra = TerritorialRule::create([
            'rule_version_id' => $versao->getKey(),
            'codigo' => 'RT-01',
            'titulo' => 'Logradouro 212',
            'tipo' => TerritorialRuleType::LogradouroCnae,
            'efeito' => TerritorialRuleEffect::Indeferir,
            'cod_log' => '212',
        ]);

        $regra->cnaes()->create(['cnae' => '4520006']);

        $this->expectExceptionMessageMatches('/UNIQUE|Duplicate/i');

        $regra->cnaes()->create(['cnae' => '4520006']);
    }

    public function test_filhos_caem_com_a_regra(): void
    {
        $versao = RuleVersion::factory()->create(['domain' => RuleDomain::Territorial]);

        $regra = TerritorialRule::create([
            'rule_version_id' => $versao->getKey(),
            'codigo' => 'RT-02-ITAIGARA',
            'titulo' => 'Loteamento Itaigara — allow-list de expresso',
            'tipo' => TerritorialRuleType::PoligonalAllowlistExpresso,
            'efeito' => TerritorialRuleEffect::PermitirExpresso,
            'camada' => 'parcelamento_poligonal:VM_PARCELAMENTO',
            'motivo_analise' => 'Solicitação cadastrada no Loteamento Itaigara.',
        ]);

        $regra->cnaes()->create(['cnae' => '4520006']);
        $regra->addresses()->create(['rotulo' => 'Hiperideal', 'cod_log' => '5209', 'numero_porta' => '347']);

        $regra->delete();

        $this->assertDatabaseCount('territorial_rule_cnaes', 0);
        $this->assertDatabaseCount('territorial_rule_addresses', 0);
    }
}
