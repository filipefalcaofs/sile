<?php

namespace Tests\Unit\Louos;

use App\Support\Louos\Quadro11aClasseVia;
use PHPUnit\Framework\TestCase;

class Quadro11aClasseViaTest extends TestCase
{
    public function test_oficiais_sao_as_sete_classes_da_louos(): void
    {
        $this->assertSame(
            ['VA I', 'VA II', 'VC I', 'VC II', 'VE', 'VL', 'VP'],
            Quadro11aClasseVia::oficiais(),
        );
    }

    public function test_grafia_do_gis_vira_grafia_da_lei(): void
    {
        $this->assertSame('VC I', Quadro11aClasseVia::oficializar('VC-I'));
        $this->assertSame('VA II', Quadro11aClasseVia::oficializar('VA-II'));
        $this->assertSame('VC II', Quadro11aClasseVia::oficializar('VC - II'));
        $this->assertSame('VL', Quadro11aClasseVia::oficializar('vl'));
        $this->assertSame('VE', Quadro11aClasseVia::oficializar('  VE  '));
    }

    public function test_classe_fora_da_lei_nao_e_promovida(): void
    {
        $this->assertNull(Quadro11aClasseVia::oficializar('VX-9'));
        $this->assertNull(Quadro11aClasseVia::oficializar(''));
        $this->assertNull(Quadro11aClasseVia::oficializar(null));
    }
}
