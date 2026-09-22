<?php

namespace Tests\Feature\Analise;

use App\Models\ViabilityRequest;
use App\Services\Analise\PerguntaLocalFicha;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * A ficha exibe a pergunta do PRÓPRIO CNAE cadastrada na planilha de
 * tratamento (relatório SEDUR 21/09/2026, item 02): para o 8211-3/00 é a P4
 * (escritório virtual/coworking), nunca uma pergunta genérica de outro CNAE.
 * CNAE sem vínculo mantém o comportamento legado (pergunta respondida na
 * simulação) e pergunta sem resposta fica pendente — nunca inventada.
 */
class PerguntaLocalFichaTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    private function solicitacaoComRespostas(array $respostas): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->protocoled()->create();
        $solicitacao->respostasTratamento = $respostas;

        return $solicitacao;
    }

    public function test_exibe_a_pergunta_do_proprio_cnae_8211(): void
    {
        $this->seedTratamentoPlanilha();

        $solicitacao = $this->solicitacaoComRespostas([4 => true, 11 => true]);

        $local = app(PerguntaLocalFicha::class)->para($solicitacao, '8211300');

        $this->assertSame(4, $local['numero']);
        $this->assertStringContainsString('escritório virtual', mb_strtolower($local['pergunta']));
        $this->assertFalse($local['pendente']);
        $this->assertSame('Sim.', $local['resposta']);
    }

    public function test_pergunta_do_cnae_sem_resposta_fica_pendente(): void
    {
        $this->seedTratamentoPlanilha();

        $solicitacao = $this->solicitacaoComRespostas([]);

        $local = app(PerguntaLocalFicha::class)->para($solicitacao, '8211300');

        $this->assertSame(4, $local['numero']);
        $this->assertStringContainsString('escritório virtual', mb_strtolower($local['pergunta']));
        $this->assertTrue($local['pendente']);
        $this->assertNull($local['resposta']);
    }

    public function test_cnae_vinculado_a_p11_mantem_textos_completos(): void
    {
        $this->seedTratamentoPlanilha();

        $solicitacao = $this->solicitacaoComRespostas([11 => false]);

        $local = app(PerguntaLocalFicha::class)->para($solicitacao, '6622300');

        $this->assertSame(11, $local['numero']);
        $this->assertStringContainsString('desenvolvida no local', mb_strtolower($local['pergunta']));
        $this->assertSame('Não, no local funcionará o escritório da empresa.', $local['resposta']);
    }
}
