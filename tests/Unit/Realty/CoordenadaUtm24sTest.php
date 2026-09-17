<?php

namespace Tests\Unit\Realty;

use App\Services\Realty\CoordenadaUtm24s;
use PHPUnit\Framework\TestCase;

class CoordenadaUtm24sTest extends TestCase
{
    public function test_converte_utm_24s_sirgas2000_para_wgs84(): void
    {
        $ponto = CoordenadaUtm24s::paraWgs84('551918', '8563162');

        $this->assertNotNull($ponto);
        $this->assertEqualsWithDelta(-12.996867, $ponto['latitude'], 0.00002);
        $this->assertEqualsWithDelta(-38.521245, $ponto['longitude'], 0.00002);
    }

    public function test_coordenada_ausente_nao_inventa_ponto(): void
    {
        $this->assertNull(CoordenadaUtm24s::paraWgs84(null, '8563162'));
        $this->assertNull(CoordenadaUtm24s::paraWgs84('551918', null));
        $this->assertNull(CoordenadaUtm24s::paraWgs84('', ''));
        $this->assertNull(CoordenadaUtm24s::paraWgs84('0', '0'));
    }
}
