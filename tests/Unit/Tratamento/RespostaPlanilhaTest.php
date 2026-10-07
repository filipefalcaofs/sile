<?php

namespace Tests\Unit\Tratamento;

use App\Services\Tratamento\RespostaPlanilha;
use PHPUnit\Framework\TestCase;

class RespostaPlanilhaTest extends TestCase
{
    public function test_booleano_legado_vale_como_opcao_um_ou_dois(): void
    {
        $sim = RespostaPlanilha::de(true);
        $nao = RespostaPlanilha::de(false);

        $this->assertTrue($sim->sim());
        $this->assertTrue($sim->contem(1));
        $this->assertFalse($sim->contem(2));
        $this->assertFalse($nao->sim());
        $this->assertTrue($nao->contem(2));
    }

    public function test_opcao_unica_e_conjunto_de_opcoes(): void
    {
        $unica = RespostaPlanilha::de(3);
        $varias = RespostaPlanilha::de([5, 1, 1, 2]);

        $this->assertNull($unica->sim());
        $this->assertTrue($unica->contem(3));
        $this->assertSame([1, 2, 5], $varias->opcoes());
        $this->assertTrue($varias->algumaDe([2, 9]));
        $this->assertFalse($varias->algumaDe([7, 8]));
    }

    public function test_opcao_um_e_dois_tambem_respondem_sim_e_nao(): void
    {
        $this->assertTrue(RespostaPlanilha::de(1)->sim());
        $this->assertFalse(RespostaPlanilha::de(2)->sim());
        $this->assertNull(RespostaPlanilha::de([1, 2])->sim());
    }

    public function test_guarda_o_valor_como_veio(): void
    {
        $this->assertTrue(RespostaPlanilha::de(true)->paraArmazenar());
        $this->assertSame(4, RespostaPlanilha::de(4)->paraArmazenar());
        $this->assertSame([1, 3], RespostaPlanilha::de([3, 1])->paraArmazenar());
    }

    public function test_texto_do_regin_vira_opcoes(): void
    {
        $this->assertSame([1, 2], RespostaPlanilha::deTexto('1,2')->opcoes());
        $this->assertSame([1, 2], RespostaPlanilha::deTexto('1 e 2')->opcoes());
        $this->assertSame([2], RespostaPlanilha::deTexto('B')->opcoes());
        $this->assertTrue(RespostaPlanilha::deTexto('Sim')->sim());
        $this->assertFalse(RespostaPlanilha::deTexto('Não')->sim());
        $this->assertFalse(RespostaPlanilha::deTexto('nao')->sim());
    }

    public function test_texto_que_nao_e_resposta_vira_nulo(): void
    {
        $this->assertNull(RespostaPlanilha::deTexto('talvez'));
        $this->assertNull(RespostaPlanilha::deTexto(''));
    }
}
