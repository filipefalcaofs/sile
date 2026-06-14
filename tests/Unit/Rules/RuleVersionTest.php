<?php

namespace Tests\Unit\Rules;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use Tests\TestCase;

/**
 * Fundação de regras versionadas (HU-019/HU-020/HU-053): os enums de domínio
 * e de vigência espelham a disciplina do GeoLayer (case + label pt-BR) e
 * acrescentam o estado rascunho (sandbox HU-143) e a sensibilidade do domínio
 * (publicação por quatro olhos). Lógica pura — sem banco.
 */
class RuleVersionTest extends TestCase
{
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
}
