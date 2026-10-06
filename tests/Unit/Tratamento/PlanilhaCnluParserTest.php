<?php

namespace Tests\Unit\Tratamento;

use App\Services\Tratamento\PlanilhaCnluParser;
use PHPUnit\Framework\TestCase;

class PlanilhaCnluParserTest extends TestCase
{
    private const BLOCO_ID3 = "ID3 relacionados no artigo 132 da Lei nº 9.148 de 2016 (LOUOS):\n\n"
        ."I – ID3-01;\nII - ID3-02 (subgrupo 2);\nIII - ID3-03 (subgrupo 2);\nIV - ID3-04;\n"
        ."\"V - ID3-05;\nVI - ID3-07;\nVII - ID3-13 (subgrupo 2);\nVIII - ID3-14 (subgrupo 2);\n"
        ."IX - ID3-17;\nX - ID3-19 (subgrupo 2);\nXI - ID3-21.\"";

    /**
     * @return list<array<int, string>>
     */
    private function linhas(): array
    {
        return [
            [], [],
            ['nR3 (nR3-08 e nR3-14)'],
            ['nRa'],
            ['nR4'],
            [self::BLOCO_ID3],
        ];
    }

    public function test_codigos_no_nivel_de_subgrupo_da_planilha(): void
    {
        $resultado = (new PlanilhaCnluParser)->interpretar($this->linhas());

        $this->assertSame(
            ['nR3-08', 'nR3-14', 'nRa', 'nR4', 'ID3-01', 'ID3-04', 'ID3-05', 'ID3-07', 'ID3-17', 'ID3-21'],
            array_column($resultado['itens'], 'codigo'),
        );
    }

    public function test_itens_subgrupo_2_ficam_aguardando_a_sedur(): void
    {
        $resultado = (new PlanilhaCnluParser)->interpretar($this->linhas());

        $this->assertSame(
            ['ID3-02', 'ID3-03', 'ID3-13', 'ID3-14', 'ID3-19'],
            $resultado['aguardando'],
        );
    }

    public function test_texto_do_item_guarda_a_celula_de_origem(): void
    {
        $resultado = (new PlanilhaCnluParser)->interpretar($this->linhas());

        $this->assertSame('nR3 (nR3-08 e nR3-14)', $resultado['itens'][0]['texto']);
    }
}
