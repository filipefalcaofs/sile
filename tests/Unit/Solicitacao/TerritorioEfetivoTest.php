<?php

namespace Tests\Unit\Solicitacao;

use App\Services\Geo\TerritoryResult;
use App\Services\Solicitacao\TerritorioManual;
use PHPUnit\Framework\TestCase;

class TerritorioEfetivoTest extends TestCase
{
    private function base(): TerritoryResult
    {
        $vazia = ['status' => 'nao_encontrado', 'nome' => null, 'propriedades' => [], 'motivo' => null, 'versao_camada' => null];

        return new TerritoryResult(
            bairro: $vazia,
            via: ['status' => 'identificado', 'nome' => 'Rua A', 'propriedades' => ['CLASSE_VIA_LOUOS' => 'VC I'], 'motivo' => null, 'versao_camada' => 'v1', 'distancia_m' => 3],
            zona: ['status' => 'identificado', 'nome' => 'ZPR 3', 'propriedades' => ['ZONA' => 'ZPR 3'], 'motivo' => null, 'versao_camada' => 'v1'],
            lote: $vazia,
            restricoes: $vazia,
        );
    }

    public function test_sem_alteracao_manual_o_territorio_nao_muda(): void
    {
        $base = $this->base();

        $this->assertSame($base, TerritorioManual::aplicar($base, null, null));
    }

    public function test_zona_manual_substitui_a_zona_identificada(): void
    {
        $efetivo = TerritorioManual::aplicar($this->base(), 'ZCMe', null);

        $this->assertSame('identificado', $efetivo->zona['status']);
        $this->assertSame('ZCMe', $efetivo->zona['nome']);
        $this->assertSame('manual', $efetivo->zona['propriedades']['fonte']);
        $this->assertSame('VC I', $efetivo->via['propriedades']['CLASSE_VIA_LOUOS']);
    }

    public function test_via_manual_substitui_a_classe_da_via(): void
    {
        $efetivo = TerritorioManual::aplicar($this->base(), null, 'VL');

        $this->assertSame('VL', $efetivo->via['propriedades']['CLASSE_VIA_LOUOS']);
        $this->assertSame('identificado', $efetivo->via['status']);
        $this->assertSame('ZPR 3', $efetivo->zona['nome']);
    }

    public function test_via_manual_sem_via_identificada_cria_a_dimensao(): void
    {
        $vazia = ['status' => 'indisponivel', 'nome' => null, 'propriedades' => [], 'motivo' => 'sem via', 'versao_camada' => null];
        $base = new TerritoryResult($vazia, $vazia + ['distancia_m' => null], $vazia, $vazia, $vazia);

        $efetivo = TerritorioManual::aplicar($base, 'ZCMe', 'VL');

        $this->assertSame('identificado', $efetivo->via['status']);
        $this->assertSame('VL', $efetivo->via['propriedades']['CLASSE_VIA_LOUOS']);
        $this->assertSame('ZCMe', $efetivo->zona['nome']);
    }
}
