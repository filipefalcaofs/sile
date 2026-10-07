<?php

namespace Tests\Unit\Tratamento;

use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;
use App\Services\Tratamento\ArmazenamentoGlp;
use App\Services\Tratamento\CondicaoRamo;
use App\Services\Tratamento\ContextoRamo;
use PHPUnit\Framework\TestCase;

class CondicaoRamoTest extends TestCase
{
    /**
     * @param  array<int, bool|int|list<int>>  $respostas
     */
    private function contexto(array $respostas = [], ?string $categoria = null, ?string $tipo = null, ?string $faixa = 'ate_1250'): ContextoRamo
    {
        return new ContextoRamo(
            respostas: $respostas,
            categoriaEmpresa: $categoria,
            tipoImovel: $tipo === null ? null : TipoImovel::fromRegin($tipo, TipoImovelCatalog::sedur200826()),
            faixa: $faixa,
        );
    }

    public function test_pergunta_sim_nao(): void
    {
        $condicao = ['perguntas' => ['7' => ['sim' => true]]];

        $this->assertTrue(CondicaoRamo::atende($condicao, $this->contexto([7 => true])));
        $this->assertTrue(CondicaoRamo::atende($condicao, $this->contexto([7 => 1])));
        $this->assertFalse(CondicaoRamo::atende($condicao, $this->contexto([7 => false])));
        $this->assertFalse(CondicaoRamo::atende($condicao, $this->contexto([])));
    }

    public function test_pergunta_por_conjunto_de_opcoes(): void
    {
        $condicao = ['perguntas' => ['22' => ['opcoes' => [1, 2]]]];

        $this->assertTrue(CondicaoRamo::atende($condicao, $this->contexto([22 => 2])));
        $this->assertTrue(CondicaoRamo::atende($condicao, $this->contexto([22 => [2, 3]])));
        $this->assertFalse(CondicaoRamo::atende($condicao, $this->contexto([22 => 3])));
    }

    public function test_pergunta_com_opcoes_marcadas_e_sem_outras(): void
    {
        $condicao = ['perguntas' => ['1' => ['opcoes' => [1, 2, 3], 'sem' => [4, 8, 9]]]];

        $this->assertTrue(CondicaoRamo::atende($condicao, $this->contexto([1 => [1, 3]])));
        $this->assertTrue(CondicaoRamo::atende($condicao, $this->contexto([1 => 2])));
        $this->assertFalse(CondicaoRamo::atende($condicao, $this->contexto([1 => [1, 4]])), 'Marcou uma opção vetada.');
        $this->assertFalse(CondicaoRamo::atende($condicao, $this->contexto([1 => 8])));
        $this->assertFalse(CondicaoRamo::atende($condicao, $this->contexto([1 => [5]])), 'Nenhuma opção pedida.');
    }

    public function test_categoria_da_empresa(): void
    {
        $condicao = ['categoria' => 'orgao_publico'];

        $this->assertTrue(CondicaoRamo::atende($condicao, $this->contexto([], 'orgao_publico')));
        $this->assertFalse(CondicaoRamo::atende($condicao, $this->contexto([], 'outra')));
        $this->assertFalse(CondicaoRamo::atende($condicao, $this->contexto([], null)));
    }

    public function test_tipo_do_imovel(): void
    {
        $this->assertTrue(CondicaoRamo::atende(['tipo_imovel' => 'residencial'], $this->contexto([], null, 'Edificação Residencial')));
        $this->assertFalse(CondicaoRamo::atende(['tipo_imovel' => 'residencial'], $this->contexto([], null, 'Galpão')));
        $this->assertTrue(CondicaoRamo::atende(['tipo_imovel' => 'galpao_container'], $this->contexto([], null, 'Container')));
        $this->assertTrue(CondicaoRamo::atende(['tipo_imovel' => 'dirige'], $this->contexto([], null, 'Edificação Residencial')));
        $this->assertTrue(CondicaoRamo::atende(['tipo_imovel' => 'comum'], $this->contexto([], null, 'Sala')));
        $this->assertTrue(CondicaoRamo::atende(['tipo_imovel' => 'comum'], $this->contexto([], null, null)));
        $this->assertFalse(CondicaoRamo::atende(['tipo_imovel' => 'comum'], $this->contexto([], null, 'Galpão')));
    }

    public function test_faixa_de_area(): void
    {
        $this->assertTrue(CondicaoRamo::atende(['faixa' => 'ate_1250'], $this->contexto([], null, null, 'ate_1250')));
        $this->assertFalse(CondicaoRamo::atende(['faixa' => 'acima_1250'], $this->contexto([], null, null, 'ate_1250')));
    }

    public function test_condicoes_combinam_por_e(): void
    {
        $condicao = ['categoria' => 'orgao_publico', 'perguntas' => ['7' => ['sim' => true]], 'tipo_imovel' => 'galpao_container'];

        $this->assertTrue(CondicaoRamo::atende($condicao, $this->contexto([7 => true], 'orgao_publico', 'Galpão')));
        $this->assertFalse(CondicaoRamo::atende($condicao, $this->contexto([7 => true], 'orgao_publico', 'Sala')));
        $this->assertSame(3, CondicaoRamo::peso($condicao));
    }

    public function test_armazenamento_glp_com_plataformas_e_capacidade(): void
    {
        // P27: A = 2 plataformas, B = 3. P28: opção 1 = 520 kg, 2 = 1.560 kg.
        $this->assertSame(1040, ArmazenamentoGlp::quilos([26 => true, 27 => 1, 28 => 1]));
        $this->assertSame(1560, ArmazenamentoGlp::quilos([26 => true, 27 => 2, 28 => 1]));
        $this->assertSame(3120, ArmazenamentoGlp::quilos([26 => true, 27 => 1, 28 => 2]));
    }

    public function test_armazenamento_glp_sem_plataformas_usa_a_capacidade_da_p29(): void
    {
        $this->assertSame(520, ArmazenamentoGlp::quilos([26 => false, 29 => 1]));
        $this->assertSame(1560, ArmazenamentoGlp::quilos([26 => false, 29 => 2]));
        $this->assertSame(6240, ArmazenamentoGlp::quilos([26 => false, 29 => 3]));
        $this->assertNull(ArmazenamentoGlp::quilos([26 => true, 27 => 1]));
        $this->assertNull(ArmazenamentoGlp::quilos([]));
    }

    public function test_condicao_de_quilos_de_glp(): void
    {
        $ate = ['glp_kg' => ['ate' => 1560]];
        $acima = ['glp_kg' => ['acima' => 1560]];

        $this->assertTrue(CondicaoRamo::atende($ate, $this->contexto([26 => true, 27 => 2, 28 => 1])));
        $this->assertFalse(CondicaoRamo::atende($acima, $this->contexto([26 => true, 27 => 2, 28 => 1])));
        $this->assertTrue(CondicaoRamo::atende($acima, $this->contexto([26 => true, 27 => 1, 28 => 2])));
        $this->assertFalse(CondicaoRamo::atende($ate, $this->contexto([])));
    }
}
