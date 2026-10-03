<?php

namespace Tests\Unit\Metricas;

use App\Support\Metricas\Percentis;
use Tests\TestCase;

class PercentisTest extends TestCase
{
    /**
     * Conjunto de referência 1..20. Os valores esperados saem da interpolação
     * linear sobre o vetor ordenado — posição k = (n - 1) * p / 100 — que é a
     * mesma convenção usada para apurar os percentis do benchmark federal.
     *
     * @return list<float>
     */
    private function valoresDeReferencia(): array
    {
        return array_map(static fn (int $i): float => (float) $i, range(1, 20));
    }

    public function test_apura_media_percentis_e_maximo_de_um_conjunto_conhecido(): void
    {
        $percentis = Percentis::de($this->valoresDeReferencia());

        $this->assertSame(20, $percentis->n);
        $this->assertEqualsWithDelta(10.5, $percentis->media, 0.0001);
        $this->assertEqualsWithDelta(10.5, $percentis->p50, 0.0001);
        $this->assertEqualsWithDelta(15.25, $percentis->p75, 0.0001);
        $this->assertEqualsWithDelta(18.1, $percentis->p90, 0.0001);
        $this->assertEqualsWithDelta(19.05, $percentis->p95, 0.0001);
        $this->assertEqualsWithDelta(19.81, $percentis->p99, 0.0001);
        $this->assertEqualsWithDelta(20.0, $percentis->maximo, 0.0001);
    }

    public function test_conjunto_vazio_devolve_estrutura_nula_sem_divisao_por_zero(): void
    {
        $percentis = Percentis::de([]);

        $this->assertSame(0, $percentis->n);
        $this->assertNull($percentis->media);
        $this->assertNull($percentis->p50);
        $this->assertNull($percentis->p75);
        $this->assertNull($percentis->p90);
        $this->assertNull($percentis->p95);
        $this->assertNull($percentis->p99);
        $this->assertNull($percentis->maximo);
    }

    public function test_conjunto_de_um_elemento_repete_o_valor_em_todos_os_percentis(): void
    {
        $percentis = Percentis::de([7.5]);

        $this->assertSame(1, $percentis->n);
        $this->assertEqualsWithDelta(7.5, $percentis->media, 0.0001);
        $this->assertEqualsWithDelta(7.5, $percentis->p50, 0.0001);
        $this->assertEqualsWithDelta(7.5, $percentis->p90, 0.0001);
        $this->assertEqualsWithDelta(7.5, $percentis->p99, 0.0001);
        $this->assertEqualsWithDelta(7.5, $percentis->maximo, 0.0001);
    }

    public function test_resultado_independe_da_ordem_de_entrada(): void
    {
        $ordenado = Percentis::de($this->valoresDeReferencia());

        $embaralhado = $this->valoresDeReferencia();
        shuffle($embaralhado);

        $this->assertEquals($ordenado->toArray(), Percentis::de($embaralhado)->toArray());
    }

    public function test_to_array_expoe_as_chaves_consumidas_pelas_telas(): void
    {
        $this->assertSame(
            ['n', 'media', 'p50', 'p75', 'p90', 'p95', 'p99', 'maximo'],
            array_keys(Percentis::de($this->valoresDeReferencia())->toArray()),
        );
    }
}
