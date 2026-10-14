<?php

namespace Tests\Unit;

use App\Services\Analise\ExplicacaoForaDoExpresso;
use PHPUnit\Framework\TestCase;

class ExplicacaoForaDoExpressoTest extends TestCase
{
    public function test_texto_longo_cita_versao_linha_regra_e_o_que_definiu_o_fluxo(): void
    {
        $texto = ExplicacaoForaDoExpresso::longa('9609-2/99', [
            'nivel' => 'medio',
            'dimensao_decisiva' => 'risco_tratamento',
            'gatilhos_acionados' => [],
            'planilha' => [
                'versao' => 'planilha-20-08-26',
                'cnae' => '9609-2/99',
                'regra' => 40,
                'regra_referencia' => 'Atualizada em 21/08/2026',
                'codigo_louos' => '07.12.13',
                'denominacao_louos' => 'Escritório (inclusive virtual), sede de empresa, grupo de salas, centro empresarial',
                'subgrupo' => 'nR2-12',
                'faixa' => 'acima_1250',
                'area_m2' => 30400.0,
                'risco_planilha' => 'MÉDIO RISCO',
                'classificacao' => 'VALIDAÇÃO',
                'origem_fluxo' => 'ramo',
                'decisao_fluxo' => 'ramo da Regra 40 (pergunta 11 = Não; área acima de 1.250 m²) → fluxo semiexpresso',
            ],
        ], 'nR2', '07.12.13');

        $this->assertStringContainsString('Onde na planilha: versão planilha-20-08-26, aba CNAES, CNAE 9609-2/99, Regra 40 (Atualizada em 21/08/2026), LOUOS 07.12.13 — Escritório', $texto);
        $this->assertStringContainsString('subgrupo nR2-12 (acima de 1.250 m², área declarada 30.400,00 m²)', $texto);
        $this->assertStringContainsString('risco MÉDIO RISCO, classificação VALIDAÇÃO.', $texto);
        $this->assertStringContainsString('O que definiu o fluxo: ramo da Regra 40 (pergunta 11 = Não; área acima de 1.250 m²) → fluxo semiexpresso.', $texto);
    }

    public function test_texto_curto_cita_a_regra(): void
    {
        $texto = ExplicacaoForaDoExpresso::curta('9609-2/99', [
            'nivel' => 'medio',
            'dimensao_decisiva' => 'risco_tratamento',
            'planilha' => ['regra' => 40],
        ]);

        $this->assertSame('CNAE 9609-2/99: risco medio (planilha de tratamento vigente, Regra 40)', $texto);
    }

    public function test_sem_planilha_o_texto_fica_como_antes(): void
    {
        $texto = ExplicacaoForaDoExpresso::longa('4712-1/00', ['nivel' => 'alto', 'dimensao_decisiva' => 'municipal']);

        $this->assertStringNotContainsString('Onde na planilha', $texto);
    }
}
