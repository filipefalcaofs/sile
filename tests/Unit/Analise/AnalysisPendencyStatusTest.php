<?php

namespace Tests\Unit\Analise;

use App\Enums\AnalysisPendencyStatus;
use PHPUnit\Framework\TestCase;

class AnalysisPendencyStatusTest extends TestCase
{
    public function test_cancelada_existe_com_rotulo(): void
    {
        $this->assertSame('Cancelada', AnalysisPendencyStatus::Cancelada->label());
        $this->assertCount(4, AnalysisPendencyStatus::cases());
    }
}
