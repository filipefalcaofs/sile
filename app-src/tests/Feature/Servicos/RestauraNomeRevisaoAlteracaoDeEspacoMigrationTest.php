<?php

namespace Tests\Feature\Servicos;

use App\Models\ServicoNomenclatura;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * A SEDUR confirmou "Revisão Alteração de Espaço" como o nome do serviço de mudança de
 * espaço. A migration de 27/10 chegou a trocá-lo por "Revisão de Espaço" nos ambientes
 * em que já rodou; esta devolve o nome, sem tocar no que foi editado na tela.
 */
class RestauraNomeRevisaoAlteracaoDeEspacoMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MESMO_MUNICIPIO = 'alteracao de endereco dentro do mesmo municipio';

    private function migration(): object
    {
        return require database_path('migrations/2026_10_28_090000_restaura_nome_revisao_alteracao_de_espaco.php');
    }

    private function linhaDeEspaco(): ServicoNomenclatura
    {
        return ServicoNomenclatura::query()->where('chave', self::MESMO_MUNICIPIO)->where('condicao', 'endereco_espaco')->firstOrFail();
    }

    public function test_nome_de_fabrica_do_espaco_e_o_da_sedur(): void
    {
        $this->assertSame('Revisão Alteração de Espaço', $this->linhaDeEspaco()->para);
    }

    public function test_devolve_o_nome_onde_a_migration_anterior_trocou_para_revisao_de_espaco(): void
    {
        $this->linhaDeEspaco()->update(['para' => 'Revisão de Espaço']);

        $this->migration()->up();

        $this->assertSame('Revisão Alteração de Espaço', $this->linhaDeEspaco()->para);
    }

    public function test_nome_editado_na_tela_nao_e_tocado(): void
    {
        $this->linhaDeEspaco()->update(['para' => 'Mudança de sala']);

        $this->migration()->up();

        $this->assertSame('Mudança de sala', $this->linhaDeEspaco()->para);
    }
}
