<?php

namespace Tests\Unit\Tratamento;

use App\Services\Tratamento\PerguntaEnunciado;
use PHPUnit\Framework\TestCase;

class PerguntaEnunciadoTest extends TestCase
{
    public function test_pergunta_sim_nao(): void
    {
        $a = PerguntaEnunciado::analisar("A atividade será desenvolvida no local?\n\n1. Sim, a atividade será desenvolvida no local.\n2. Não, no local funcionará  o escritório da empresa.");

        $this->assertSame('sim_nao', $a['tipo']);
        $this->assertCount(2, $a['opcoes']);
    }

    public function test_pergunta_de_opcao_unica_com_numeros(): void
    {
        $a = PerguntaEnunciado::analisar("Exibir apenas SE RESPONDIDO \"SIM\" na pergunta 21:\nNo local funcionará:\n1. Lavanderia  Industrial\n2. Lavanderia não industrial");

        $this->assertSame('opcao_unica', $a['tipo']);
        $this->assertSame(['1', '2'], array_column($a['opcoes'], 'rotulo'));
        $this->assertSame('Lavanderia  Industrial', $a['opcoes'][0]['texto']);
    }

    public function test_pergunta_de_opcao_unica_com_letras(): void
    {
        $a = PerguntaEnunciado::analisar("Quantas plataformas têm o estabelecimento?\nA. 2 plataformas \nB. 3 plataformas");

        $this->assertSame('opcao_unica', $a['tipo']);
        $this->assertSame(['A', 'B'], array_column($a['opcoes'], 'rotulo'));
    }

    public function test_pergunta_de_multipla_escolha(): void
    {
        $a = PerguntaEnunciado::analisar("Caso o estabelecimento não seja uma sede, selecione quais das atividades abaixo serão exercidas no local:\n\n1. Serviços de arquivamento;\n2. Serviços de organização;\n3. Coworking");

        $this->assertSame('multipla', $a['tipo']);
        $this->assertCount(3, $a['opcoes']);
    }

    public function test_dependencia_da_pergunta_exibida_apenas_apos_outra_resposta(): void
    {
        $this->assertSame(
            ['pergunta' => 2, 'sim' => true],
            PerguntaEnunciado::dependencia("EXIBIR APENAS SE RESPONDIDO \"SIM\" na pergunta 2:\n\nA atividade será desenvolvida de modo artesanal?"),
        );
        $this->assertSame(
            ['pergunta' => 4, 'sim' => false],
            PerguntaEnunciado::dependencia('Exibir apenas SE RESPONDIDO "NÃO" na pergunta 4: selecione'),
        );
        $this->assertSame(
            ['pergunta' => 26, 'sim' => false],
            PerguntaEnunciado::dependencia('Se respondido Não na pergunta 26, exibir a pergunta seguinte:'),
        );
        $this->assertSame(
            ['pergunta' => 25, 'sim' => true],
            PerguntaEnunciado::dependencia("Se respondido SIM na pergunta 25, exibir a pergunta a seguir:\nTrata-se de revenda?"),
        );
    }

    public function test_pergunta_sem_dependencia(): void
    {
        $this->assertNull(PerguntaEnunciado::dependencia('A atividade será desenvolvida no local?'));
    }

    public function test_enunciado_sem_opcoes_continua_sim_nao(): void
    {
        $a = PerguntaEnunciado::analisar('Identifique entre as opções a seguir os serviços ou as atividades que serão prestados:');

        $this->assertSame('sim_nao', $a['tipo']);
        $this->assertSame([], $a['opcoes']);
    }
}
