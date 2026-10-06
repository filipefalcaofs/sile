<?php

namespace Tests\Unit\Support;

use App\Support\AreaMetros;
use PHPUnit\Framework\TestCase;

class AreaMetrosTest extends TestCase
{
    public function test_formata_area_em_portugues_sem_ponto_decimal(): void
    {
        $this->assertSame('1.250', AreaMetros::formatar('1250.00'));
        $this->assertSame('1.250,01', AreaMetros::formatar('1250.01'));
        $this->assertSame('1.250,5', AreaMetros::formatar(1250.5));
        $this->assertSame('156.636', AreaMetros::formatar(156636));
        $this->assertNull(AreaMetros::formatar(null));
        $this->assertNull(AreaMetros::formatar(''));
    }
}
