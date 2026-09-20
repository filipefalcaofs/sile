<?php

namespace Tests\Unit\Analise;

use App\Services\Analise\QuadrosFichaChecklist;
use Tests\TestCase;

/**
 * Checklist ilustrativo dos quadros da LOUOS por CNAE na ficha: cada quadro
 * vira uma linha com estado (permitido/não permitido/condicionado/pendente)
 * derivado do que o motor gravou — nunca recomputado nem inventado.
 */
class QuadrosFichaChecklistTest extends TestCase
{
    public function test_quadro_10_proibido_e_quadro_11a_sem_vedacao(): void
    {
        $itens = app(QuadrosFichaChecklist::class)->para($this->consulta(
            quadro10: ['status' => 'identificado', 'permissao' => 'proibido'],
            quadro11a: ['status' => 'identificado', 'condicoes' => ['Sim'], 'classe_via' => 'VL'],
        ));

        $this->assertCount(2, $itens);
        $this->assertSame('nao_permitido', $itens[0]['estado']);
        $this->assertSame('quadro10', $itens[0]['key']);
        $this->assertSame('permitido', $itens[1]['estado']);
        $this->assertSame('quadro11a', $itens[1]['key']);
    }

    public function test_quadro_10_permite_mas_quadro_11a_veda(): void
    {
        // Cenário do processo 4: Q10 permite, 11-A veda (Não).
        $itens = app(QuadrosFichaChecklist::class)->para($this->consulta(
            quadro10: ['status' => 'identificado', 'permissao' => 'permitido'],
            quadro11a: ['status' => 'identificado', 'condicoes' => ['Não'], 'classe_via' => 'VL'],
        ));

        $this->assertSame('permitido', $itens[0]['estado']);
        $this->assertSame('nao_permitido', $itens[1]['estado']);
    }

    public function test_quadro_11a_com_r_encaminha_a_cnlu_e_fica_pendente(): void
    {
        $itens = app(QuadrosFichaChecklist::class)->para($this->consulta(
            quadro10: ['status' => 'identificado', 'permissao' => 'permitido'],
            quadro11a: ['status' => 'identificado', 'condicoes' => ['R'], 'classe_via' => 'VL'],
        ));

        $this->assertSame('pendente', $itens[1]['estado']);
    }

    public function test_quadro_10_condicionado_e_quadro_11a_com_condicao_textual(): void
    {
        $itens = app(QuadrosFichaChecklist::class)->para($this->consulta(
            quadro10: ['status' => 'identificado', 'permissao' => 'permitido_condicionado'],
            quadro11a: ['status' => 'identificado', 'condicoes' => ['Recuo de 5m'], 'classe_via' => 'VA-I'],
        ));

        $this->assertSame('condicionado', $itens[0]['estado']);
        $this->assertSame('condicionado', $itens[1]['estado']);
    }

    public function test_quadro_nao_identificado_na_base_fica_pendente(): void
    {
        $itens = app(QuadrosFichaChecklist::class)->para($this->consulta(
            quadro10: ['status' => 'nao_encontrado', 'permissao' => null],
            quadro11a: ['status' => 'indisponivel', 'condicoes' => []],
        ));

        $this->assertSame('pendente', $itens[0]['estado']);
        $this->assertSame('pendente', $itens[1]['estado']);
    }

    /**
     * Monta o item `consulta` no formato do engine_snapshot da ficha: o
     * `enquadramento` é o EnquadramentoResult::toArray() (uso + quadros +
     * consolidado), o `territorio` traz a zona.
     *
     * @param  array<string, mixed>  $quadro10
     * @param  array<string, mixed>  $quadro11a
     * @return array<string, mixed>
     */
    private function consulta(array $quadro10, array $quadro11a): array
    {
        return [
            'enquadramento' => [
                'enquadramento' => ['status' => 'identificado', 'grupo' => 'nR2', 'subgrupo' => 'nR2-12'],
                'quadro10' => $quadro10,
                'quadro11a' => $quadro11a,
            ],
            'territorio' => ['zona' => ['status' => 'identificado', 'nome' => 'ZPR 3']],
        ];
    }
}
