<?php

namespace Tests\Unit\Geo;

use App\Services\Geo\ZonaGeometriaColetor;
use App\Services\Geo\ZonaIntersecaoMedidor;
use App\Services\Geo\ZonaLimitrofeService;
use App\Services\Geo\ZonaPorcaoDecisor;
use PHPUnit\Framework\TestCase;

class ZonaLimitrofeServiceTest extends TestCase
{
    public function test_sem_poligono_nao_consulta_zona_nem_escolhe(): void
    {
        $coletor = $this->createMock(ZonaGeometriaColetor::class);
        $coletor->expects($this->never())->method('noPoligono');

        $medidor = $this->createMock(ZonaIntersecaoMedidor::class);
        $medidor->expects($this->never())->method('medir');

        $resultado = (new ZonaLimitrofeService(new ZonaPorcaoDecisor, $coletor, $medidor))->avaliar(null);

        $this->assertSame('sem_poligono', $resultado->status);
        $this->assertNull($resultado->zonaVencedora);
        $this->assertNull($resultado->fonte);
        $this->assertStringContainsString('geometria do terreno', (string) $resultado->motivo);
    }

    public function test_linha_nao_e_poligono_de_lote(): void
    {
        $coletor = $this->createMock(ZonaGeometriaColetor::class);
        $coletor->expects($this->never())->method('noPoligono');
        $medidor = $this->createMock(ZonaIntersecaoMedidor::class);

        $resultado = (new ZonaLimitrofeService(new ZonaPorcaoDecisor, $coletor, $medidor))->avaliar([
            'type' => 'LineString',
            'coordinates' => [[0, 0], [1, 1]],
        ]);

        $this->assertSame('sem_poligono', $resultado->status);
    }
}
