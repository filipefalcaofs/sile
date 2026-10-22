<?php

namespace Tests\Unit\Realty;

use App\Services\Realty\LocalizacaoInscricao;
use PHPUnit\Framework\TestCase;

class LocalizacaoInscricaoUsoTest extends TestCase
{
    public function test_utilizacao_nao_residencial_e_reconhecida_com_ou_sem_acento(): void
    {
        $this->assertTrue((new LocalizacaoInscricao('localizada', utilizacao: 'NÃO RESIDENCIAL VERTICAL'))->usoNaoResidencial());
        $this->assertTrue((new LocalizacaoInscricao('localizada', utilizacao: 'nao residencial horizontal'))->usoNaoResidencial());
    }

    public function test_residencial_misto_e_sem_dado_nao_contam_como_nao_residencial(): void
    {
        $this->assertFalse((new LocalizacaoInscricao('localizada', utilizacao: 'RESIDENCIAL VERTICAL'))->usoNaoResidencial());
        $this->assertFalse((new LocalizacaoInscricao('localizada', utilizacao: 'MISTO'))->usoNaoResidencial());
        $this->assertFalse((new LocalizacaoInscricao('localizada'))->usoNaoResidencial());
    }
}
