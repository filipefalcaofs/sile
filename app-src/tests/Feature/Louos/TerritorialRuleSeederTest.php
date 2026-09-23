<?php

namespace Tests\Feature\Louos;

use App\Enums\RuleDomain;
use App\Enums\TerritorialRuleEffect;
use App\Enums\TerritorialRuleType;
use App\Models\DecisionText;
use App\Models\RuleVersion;
use App\Models\TerritorialRule;
use Database\Seeders\DecisionTextSeeder;
use Database\Seeders\TerritorialRuleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Golden case da carga oficial das regras territoriais (pacote SEDUR
 * 2026-08-28): a versão vigente do domínio `territorial` nasce com as regras
 * do documento, deduplicadas e com os bloqueios honestos das poligonais sem
 * atributo confirmado.
 */
class TerritorialRuleSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function seedar(): void
    {
        $this->seed(DecisionTextSeeder::class);
        $this->seed(TerritorialRuleSeeder::class);
    }

    public function test_publica_versao_vigente_do_dominio_territorial(): void
    {
        $this->seedar();

        $versao = RuleVersion::vigente(RuleDomain::Territorial)->first();

        $this->assertNotNull($versao);
        $this->assertSame('sedur-2026-08-28-regras-territoriais', $versao->version);
        $this->assertSame(9, TerritorialRule::query()->where('rule_version_id', $versao->getKey())->count());
    }

    public function test_rt01_tem_21_cnaes_deduplicados_e_texto_legal_vinculado(): void
    {
        $this->seedar();

        $rt01 = TerritorialRule::query()->where('codigo', 'RT-01')->sole();

        $this->assertSame('212', $rt01->cod_log);
        $this->assertSame(TerritorialRuleType::LogradouroCnae, $rt01->tipo);
        $this->assertSame(TerritorialRuleEffect::Indeferir, $rt01->efeito);
        $this->assertCount(21, $rt01->cnaes);
        $this->assertContains('4520006', $rt01->cnaes->pluck('cnae'));
        $this->assertContains('3831999', $rt01->cnaes->pluck('cnae'));
        $this->assertNotNull($rt01->decision_text_id);
        $this->assertSame(
            'territorial.indeferimento.logradouro_cnae',
            DecisionText::query()->find($rt01->decision_text_id)?->key,
        );
    }

    public function test_itaigara_tem_os_11_enderecos_da_allowlist(): void
    {
        $this->seedar();

        $itaigara = TerritorialRule::query()->where('codigo', 'RT-02-ITAIGARA')->sole();

        $this->assertCount(11, $itaigara->addresses);
        $this->assertFalse($itaigara->ativo);

        $hiperideal = $itaigara->addresses()->where('rotulo', 'Hiperideal')->sole();
        $this->assertSame('5209', $hiperideal->cod_log);
        $this->assertSame('347', $hiperideal->numero_porta);
    }

    public function test_poligonais_sem_atributo_confirmado_nascem_inativas(): void
    {
        $this->seedar();

        $this->assertFalse(TerritorialRule::query()->where('codigo', 'RT-02-VELA-BRANCA')->sole()->ativo);
        $this->assertFalse(TerritorialRule::query()->where('codigo', 'RT-03-ABM')->sole()->ativo);
        $this->assertTrue(TerritorialRule::query()->where('codigo', 'RT-03-ZEM')->sole()->ativo);
        $this->assertTrue(TerritorialRule::query()->where('codigo', 'RT-03-ZPAM')->sole()->ativo);
        $this->assertTrue(TerritorialRule::query()->where('codigo', 'RT-04-ZUE')->sole()->ativo);
    }

    public function test_observacoes_do_quadro10_tem_suas_allowlists(): void
    {
        $this->seedar();

        $obsA = TerritorialRule::query()->where('codigo', 'RT-05-A')->sole();
        $obsB = TerritorialRule::query()->where('codigo', 'RT-05-B')->sole();

        $this->assertSame('ZCMu-2', $obsA->zona_alvo);
        $this->assertCount(3, $obsA->addresses);
        $this->assertSame(['Calçada', 'São Cristóvão'], $obsA->addresses()->whereNotNull('bairro')->orderBy('bairro')->pluck('bairro')->all());

        $this->assertSame('ZCLMu', $obsB->zona_alvo);
        $this->assertCount(8, $obsB->addresses);
        $this->assertContains('4661', $obsB->addresses->pluck('cod_log'));
    }

    public function test_reseed_e_idempotente(): void
    {
        $this->seedar();
        $this->seed(TerritorialRuleSeeder::class);

        $versao = RuleVersion::vigente(RuleDomain::Territorial)->sole();

        $this->assertSame(9, TerritorialRule::query()->where('rule_version_id', $versao->getKey())->count());
        $this->assertSame(21, TerritorialRule::query()->where('codigo', 'RT-01')->sole()->cnaes()->count());
    }
}
