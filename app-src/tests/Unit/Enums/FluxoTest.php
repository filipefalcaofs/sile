<?php

namespace Tests\Unit\Enums;

use App\Enums\Fluxo;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rótulos do encaminhamento por risco: "expresso" é elegibilidade, não desfecho.
 */
class FluxoTest extends TestCase
{
    #[Test]
    public function risco_label_distingue_elegibilidade_de_desfecho(): void
    {
        $this->assertSame('Elegível ao expresso (risco)', Fluxo::Expresso->riscoLabel());
        $this->assertSame('Análise técnica', Fluxo::Analise->riscoLabel());
    }
}
