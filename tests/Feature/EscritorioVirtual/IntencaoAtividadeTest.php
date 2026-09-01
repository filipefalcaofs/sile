<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\IntencaoAtividade;
use App\Models\Cnae;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Intencao por atividade na solicitacao (RN-AA-05b): a alteracao de atividade
 * economica precisa distinguir o que esta sendo incluido do que esta sendo
 * excluido. O pivot guardava so `is_primary`. `null` na intencao significa
 * solicitacao que nao declara intencao por atividade — primeiro
 * estabelecimento, renovacao —, nao "manter".
 */
class IntencaoAtividadeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_solicitacao_sem_intencao_declarada_nao_tem_exclusoes(): void
    {
        $request = ViabilityRequest::factory()->create();
        $cnae = Cnae::factory()->create();
        $request->cnaes()->attach($cnae->id, ['is_primary' => true]);

        $this->assertTrue($request->cnaesParaExcluir()->isEmpty());
        $this->assertFalse($request->exclusivamenteExclusao());
    }

    public function test_devolve_apenas_os_cnaes_marcados_para_exclusao(): void
    {
        $request = ViabilityRequest::factory()->create();
        $manter = Cnae::factory()->create();
        $sair = Cnae::factory()->create();

        $request->cnaes()->attach($manter->id, ['is_primary' => true, 'intencao' => IntencaoAtividade::Incluir->value]);
        $request->cnaes()->attach($sair->id, ['is_primary' => false, 'intencao' => IntencaoAtividade::Excluir->value]);

        $this->assertSame([$sair->code], $request->cnaesParaExcluir()->pluck('code')->all());
    }

    public function test_exclusivamente_exclusao_quando_todos_saem(): void
    {
        $request = ViabilityRequest::factory()->create();

        foreach (Cnae::factory()->count(2)->create() as $i => $cnae) {
            $request->cnaes()->attach($cnae->id, ['is_primary' => $i === 0, 'intencao' => IntencaoAtividade::Excluir->value]);
        }

        $this->assertTrue($request->exclusivamenteExclusao());
    }

    public function test_solicitacao_mista_nao_e_exclusivamente_exclusao(): void
    {
        $request = ViabilityRequest::factory()->create();
        $entra = Cnae::factory()->create();
        $sai = Cnae::factory()->create();

        $request->cnaes()->attach($entra->id, ['is_primary' => true, 'intencao' => IntencaoAtividade::Incluir->value]);
        $request->cnaes()->attach($sai->id, ['is_primary' => false, 'intencao' => IntencaoAtividade::Excluir->value]);

        $this->assertFalse($request->exclusivamenteExclusao());
    }

    public function test_solicitacao_sem_cnae_nenhum_nao_e_exclusivamente_exclusao(): void
    {
        // Guarda contra a armadilha do "todos" sobre conjunto vazio, que em PHP
        // e verdadeiro e faria uma solicitacao vazia parecer isenta.
        $request = ViabilityRequest::factory()->create();

        $this->assertFalse($request->exclusivamenteExclusao());
    }
}
