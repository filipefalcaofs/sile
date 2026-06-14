<?php

namespace Tests\Unit\Rules;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\RuleVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fundação de regras versionadas (HU-019/HU-020/HU-053): os enums de domínio
 * e de vigência espelham a disciplina do GeoLayer (case + label pt-BR) e
 * acrescentam o estado rascunho (sandbox HU-143) e a sensibilidade do domínio
 * (publicação por quatro olhos). Os scopes (vigente/naData/versao) reproduzem a
 * vigência por linhas (datas) — provados em SQLite, sem PostGIS.
 */
class RuleVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_enums_expoem_label_e_sensibilidade(): void
    {
        $this->assertSame('risco_municipal', RuleDomain::RiscoMunicipal->value);
        $this->assertSame('risco_sanitario', RuleDomain::RiscoSanitario->value);
        $this->assertSame('condicionante', RuleDomain::Condicionante->value);

        $this->assertSame('Risco municipal (Decreto 32.636/2020)', RuleDomain::RiscoMunicipal->label());
        $this->assertSame('Risco sanitário (VISA)', RuleDomain::RiscoSanitario->label());
        $this->assertSame('Condicionante', RuleDomain::Condicionante->label());

        $this->assertTrue(RuleDomain::RiscoMunicipal->isSensitive());
        $this->assertTrue(RuleDomain::RiscoSanitario->isSensitive());
        $this->assertFalse(RuleDomain::Condicionante->isSensitive());

        $this->assertSame('rascunho', RuleVersionStatus::Rascunho->value);
        $this->assertSame('vigente', RuleVersionStatus::Vigente->value);
        $this->assertSame('substituida', RuleVersionStatus::Substituida->value);

        $this->assertSame('Rascunho', RuleVersionStatus::Rascunho->label());
        $this->assertSame('Vigente', RuleVersionStatus::Vigente->label());
        $this->assertSame('Substituída', RuleVersionStatus::Substituida->label());
    }

    public function test_casts_convertem_dominio_status_e_datas(): void
    {
        $versao = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'status' => RuleVersionStatus::Vigente,
            'valid_from' => '2020-01-01',
            'published_at' => '2020-01-01 10:00:00',
        ]);

        $fresh = $versao->fresh();

        $this->assertInstanceOf(RuleDomain::class, $fresh->domain);
        $this->assertInstanceOf(RuleVersionStatus::class, $fresh->status);
        $this->assertInstanceOf(Carbon::class, $fresh->valid_from);
        $this->assertInstanceOf(Carbon::class, $fresh->published_at);
    }

    public function test_scope_vigente_retorna_versao_sem_valid_to(): void
    {
        RuleVersion::factory()->substituida()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-antigo',
            'valid_from' => '2018-01-01',
            'valid_to' => '2020-01-01',
        ]);

        // Rascunho coexiste com a vigente e também tem valid_to nulo — por isso
        // vigente() filtra status=vigente (diferença de disciplina vs GeoLayer).
        RuleVersion::factory()->rascunho()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-rascunho',
        ]);

        $vigente = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-32636-2020',
            'valid_from' => '2020-01-01',
            'valid_to' => null,
        ]);

        $resultado = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->get();

        $this->assertCount(1, $resultado);
        $this->assertTrue($resultado->first()->is($vigente));
    }

    public function test_scope_na_data_retorna_versao_da_epoca(): void
    {
        $antiga = RuleVersion::factory()->substituida()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-antigo',
            'valid_from' => '2018-01-01',
            'valid_to' => '2020-01-01',
        ]);

        RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-32636-2020',
            'valid_from' => '2020-01-01',
            'valid_to' => null,
        ]);

        $resultado = RuleVersion::naData('risco_municipal', Carbon::parse('2019-01-01'))->get();

        $this->assertCount(1, $resultado);
        $this->assertTrue($resultado->first()->is($antiga));
    }

    public function test_scope_versao_filtra_por_dominio_e_versao(): void
    {
        $alvo = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-32636-2020',
        ]);

        // Mesmo número de versão em outro domínio não pode casar (escopo por domínio).
        RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoSanitario,
            'version' => 'decreto-32636-2020',
        ]);

        $resultado = RuleVersion::versao(RuleDomain::RiscoMunicipal, 'decreto-32636-2020')->get();

        $this->assertCount(1, $resultado);
        $this->assertTrue($resultado->first()->is($alvo));
    }
}
