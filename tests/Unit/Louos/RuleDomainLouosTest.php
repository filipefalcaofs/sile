<?php

namespace Tests\Unit\Louos;

use App\Enums\Quadro10Permissao;
use App\Enums\ResultadoViabilidade;
use App\Enums\RuleDomain;
use Tests\TestCase;

/**
 * Contrato dos domínios LOUOS e dos enums de resultado da fundação do motor
 * (05-01). Os quatro Quadros (7/10/11/11A) entram como domínios de regra
 * versionada SENSÍVEIS (publicação quatro olhos) sem tocar os domínios de risco
 * da Fase 6. ResultadoViabilidade é o veredito consolidado do parecer (HU-044)
 * e Quadro10Permissao é o dado de permissão da atividade na zona (HU-039).
 */
class RuleDomainLouosTest extends TestCase
{
    public function test_dominios_louos_existem_e_sao_sensiveis(): void
    {
        $this->assertSame('louos_quadro7', RuleDomain::LouosQuadro7->value);
        $this->assertSame('louos_quadro10', RuleDomain::LouosQuadro10->value);
        $this->assertSame('louos_quadro11', RuleDomain::LouosQuadro11->value);
        $this->assertSame('louos_quadro11a', RuleDomain::LouosQuadro11a->value);

        $this->assertSame('Quadro 7 da LOUOS (enquadramento por área)', RuleDomain::LouosQuadro7->label());
        $this->assertSame('Quadro 10 da LOUOS (permissão por zona)', RuleDomain::LouosQuadro10->label());
        $this->assertSame('Quadro 11 da LOUOS (condições pela via)', RuleDomain::LouosQuadro11->label());
        $this->assertSame('Quadro 11A da LOUOS (condições complementares pela via)', RuleDomain::LouosQuadro11a->label());

        $this->assertTrue(RuleDomain::LouosQuadro7->isSensitive());
        $this->assertTrue(RuleDomain::LouosQuadro10->isSensitive());
        $this->assertTrue(RuleDomain::LouosQuadro11->isSensitive());
        $this->assertTrue(RuleDomain::LouosQuadro11a->isSensitive());
    }

    public function test_dominios_de_risco_permanecem_inalterados(): void
    {
        $this->assertTrue(RuleDomain::RiscoMunicipal->isSensitive());
        $this->assertTrue(RuleDomain::RiscoSanitario->isSensitive());
        $this->assertFalse(RuleDomain::Condicionante->isSensitive());

        $this->assertSame('Risco municipal (Decreto 32.636/2020)', RuleDomain::RiscoMunicipal->label());
        $this->assertSame('Risco sanitário (VISA)', RuleDomain::RiscoSanitario->label());
        $this->assertSame('Condicionante', RuleDomain::Condicionante->label());
    }

    public function test_enums_de_resultado_e_permissao_expoem_label(): void
    {
        $this->assertSame('permitido', ResultadoViabilidade::Permitido->value);
        $this->assertSame('permitido_com_condicoes', ResultadoViabilidade::PermitidoComCondicoes->value);
        $this->assertSame('nao_permitido', ResultadoViabilidade::NaoPermitido->value);
        $this->assertSame('pendente', ResultadoViabilidade::Pendente->value);

        $this->assertSame('Permitido', ResultadoViabilidade::Permitido->label());
        $this->assertSame('Permitido com condições', ResultadoViabilidade::PermitidoComCondicoes->label());
        $this->assertSame('Não permitido', ResultadoViabilidade::NaoPermitido->label());
        $this->assertSame('Pendente de análise técnica', ResultadoViabilidade::Pendente->label());

        $this->assertSame('permitido', Quadro10Permissao::Permitido->value);
        $this->assertSame('permitido_condicionado', Quadro10Permissao::PermitidoCondicionado->value);
        $this->assertSame('proibido', Quadro10Permissao::Proibido->value);

        $this->assertSame('Permitido', Quadro10Permissao::Permitido->label());
        $this->assertSame('Permitido condicionado', Quadro10Permissao::PermitidoCondicionado->label());
        $this->assertSame('Proibido', Quadro10Permissao::Proibido->label());
    }
}
