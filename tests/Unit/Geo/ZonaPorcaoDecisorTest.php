<?php

namespace Tests\Unit\Geo;

use App\Services\Geo\ZonaPorcaoDecisor;
use PHPUnit\Framework\TestCase;

class ZonaPorcaoDecisorTest extends TestCase
{
    private ZonaPorcaoDecisor $decisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decisor = new ZonaPorcaoDecisor;
    }

    public function test_prevalece_a_maior_area_sem_corte_percentual(): void
    {
        $resultado = $this->decisor->decidir([
            ['zona' => 'ZPL 3', 'area_m2' => 49.5, 'linear' => false],
            ['zona' => 'ZPR 3', 'area_m2' => 50.5, 'linear' => false],
        ]);

        $this->assertSame('identificado', $resultado->status);
        $this->assertSame('ZPR 3', $resultado->zonaVencedora);
        $this->assertSame('maior_porcao_do_poligono', $resultado->fonte);
        $this->assertSame([], $resultado->empatadas);
        $this->assertSame('ZPR 3', $resultado->porcoes[0]['zona']);
        $this->assertSame(50.5, $resultado->porcoes[0]['area_m2']);
        $this->assertSame(50.5, $resultado->porcoes[0]['percentual']);
        $this->assertSame(49.5, $resultado->porcoes[1]['percentual']);
    }

    public function test_qualquer_diferenca_de_area_decide(): void
    {
        $resultado = $this->decisor->decidir([
            ['zona' => 'A', 'area_m2' => 10.0, 'linear' => false],
            ['zona' => 'B', 'area_m2' => 10.002, 'linear' => false],
        ]);

        $this->assertSame('identificado', $resultado->status);
        $this->assertSame('B', $resultado->zonaVencedora);
    }

    public function test_diferenca_menor_que_um_porcento_prevalece_a_maior(): void
    {
        $resultado = $this->decisor->decidir([
            ['zona' => 'ZPL 3', 'area_m2' => 49.8, 'linear' => false],
            ['zona' => 'ZPR 3', 'area_m2' => 50.2, 'linear' => false],
        ]);

        $this->assertSame('identificado', $resultado->status);
        $this->assertSame('ZPR 3', $resultado->zonaVencedora);
    }

    public function test_diferenca_acima_de_um_milimetro_quadrado_prevalece_a_maior(): void
    {
        $resultado = $this->decisor->decidir([
            ['zona' => 'A', 'area_m2' => 100.0, 'linear' => false],
            ['zona' => 'B', 'area_m2' => 100.00005, 'linear' => false],
        ]);

        $this->assertSame('identificado', $resultado->status);
        $this->assertSame('B', $resultado->zonaVencedora);
    }

    public function test_empate_so_quando_a_diferenca_e_milimetrica(): void
    {
        $resultado = $this->decisor->decidir([
            ['zona' => 'ZPR 3', 'area_m2' => 80.0, 'linear' => false],
            ['zona' => 'ZPL 3', 'area_m2' => 80.0000004, 'linear' => false],
        ]);

        $this->assertSame('empate', $resultado->status);
        $this->assertNull($resultado->zonaVencedora);
        $this->assertEqualsCanonicalizing(['ZPR 3', 'ZPL 3'], $resultado->empatadas);
    }

    public function test_empate_exato_nao_escolhe_zona(): void
    {
        $resultado = $this->decisor->decidir([
            ['zona' => 'ZPR 3', 'area_m2' => 40.0, 'linear' => false],
            ['zona' => 'ZPL 3', 'area_m2' => 40.0, 'linear' => false],
            ['zona' => 'ZEIS', 'area_m2' => 10.0, 'linear' => false],
        ]);

        $this->assertSame('empate', $resultado->status);
        $this->assertNull($resultado->zonaVencedora);
        $this->assertEqualsCanonicalizing(['ZPR 3', 'ZPL 3'], $resultado->empatadas);
        $this->assertCount(3, $resultado->porcoes);
        $this->assertStringContainsString('ZPR 3', (string) $resultado->motivo);
        $this->assertStringContainsString('ZPL 3', (string) $resultado->motivo);
    }

    public function test_zona_linear_nao_entra_na_comparacao(): void
    {
        $resultado = $this->decisor->decidir([
            ['zona' => 'ZCLMU', 'area_m2' => 999.0, 'linear' => true],
            ['zona' => 'ZPR 3', 'area_m2' => 80.0, 'linear' => false],
            ['zona' => 'ZEIS', 'area_m2' => 20.0, 'linear' => false],
        ]);

        $this->assertSame('ZPR 3', $resultado->zonaVencedora);
        $this->assertSame(['ZPR 3', 'ZEIS'], array_column($resultado->porcoes, 'zona'));
    }

    public function test_sem_intersecao_de_area_nao_escolhe_zona(): void
    {
        $resultado = $this->decisor->decidir([
            ['zona' => 'ZCLMU', 'area_m2' => 0.0, 'linear' => true],
        ]);

        $this->assertSame('sem_intersecao', $resultado->status);
        $this->assertNull($resultado->zonaVencedora);
        $this->assertSame([], $resultado->porcoes);
    }
}
