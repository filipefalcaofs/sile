<?php

namespace Tests\Feature;

use Tests\TestCase;

class IdentidadeDoSistemaTest extends TestCase
{
    public function test_o_nome_oficial_do_sistema_e_viabiliza(): void
    {
        $this->assertSame('Viabiliza', config('app.name'));
        $this->assertSame('Viabiliza', config('mail.from.name'));
    }
}
