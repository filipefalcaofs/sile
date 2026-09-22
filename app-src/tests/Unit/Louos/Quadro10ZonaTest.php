<?php

namespace Tests\Unit\Louos;

use App\Support\Louos\Quadro10Zona;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Quadro10ZonaTest extends TestCase
{
    #[DataProvider('aliasesOficiais')]
    public function test_alias_gis_vira_grafia_do_quadro_10(string $bruta, string $oficial): void
    {
        $this->assertSame($oficial, Quadro10Zona::oficializar($bruta));
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function aliasesOficiais(): array
    {
        return [
            ['ZPR 1', 'ZPR 1'],
            ['ZPR-1', 'ZPR 1'],
            ['ZPR_1', 'ZPR 1'],
            ['ZPR 3', 'ZPR 3'],
            ['ZPR-3', 'ZPR 3'],
            ['ZEIS-2', 'ZEIS 2'],
            ['ZDE_1', 'ZDE 1'],
            ['ZCMe 2', 'ZCMe 2'],
            ['ZCME 2', 'ZCMe 2'],
            ['ZCMe 1/01', 'ZCMe 1/01'],
            ['ZCMe - CA', 'ZCMe - CA'],
            ['ZCMu 1 - IPITANGA', 'ZCMu 1 - IPITANGA'],
            ['ZCLMe', 'ZCLMe'],
            ['ZIT', 'ZIT'],
        ];
    }

    public function test_codigo_inventado_nao_vira_zona_oficial(): void
    {
        $this->assertSame('ZCN-1', Quadro10Zona::oficializar('ZCN-1'));
        $this->assertNull(Quadro10Zona::oficializar(null));
        $this->assertNull(Quadro10Zona::oficializar(''));
    }

    public function test_lista_oficial_tem_as_21_zonas_do_pdf(): void
    {
        $this->assertCount(21, Quadro10Zona::oficiais());
        $this->assertContains('ZCMe 2', Quadro10Zona::oficiais());
        $this->assertNotContains('ZCN-1', Quadro10Zona::oficiais());
        $this->assertNotContains('ZPAM', Quadro10Zona::oficiais());
    }
}
