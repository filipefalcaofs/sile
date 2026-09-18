<?php

namespace Tests\Unit\Louos;

use App\Services\Louos\LouosQuadro7EnquadramentosConverter;
use Tests\TestCase;

/**
 * Conversão da planilha operacional CNAE→LOUOS (20.08.26) para o CSV do
 * Quadro 7 (cnae,grupo,subgrupo,area_min,area_max). O motor só admite uma
 * faixa não sobreposta por CNAE: o escritório genérico 07.12.13 entra só
 * quando é o único uso; 9900-8/00 fica de fora do catálogo 2.3.
 */
class LouosQuadro7EnquadramentosConverterTest extends TestCase
{
    private const HEADER = 'cnae,denominacao,risco,regra_atualizacao,codigo_louos,denominacao_louos,enquadramento_texto,enquadramento1,ate_m2_1,enquadramento2,ate_m2_2,enquadramento3,acima_m2,codigo_tll,especificacao_tll,classificacao';

    public function test_minimercado_usa_comercio_e_descarta_escritorio_generico(): void
    {
        $csv = $this->csvTemporario([
            self::HEADER,
            $this->linha('4712-1/00', '07.01.05', 'nR1-01 (Até 350) ; nR2-01 (Acima de 350)', 'nR1-01', '350,00', 'nR2-01', '350,01', '-', '-'),
            $this->linha('4712-1/00', '07.12.13', 'nR1-12 (até 1250) ; nR2-12 (acima de 1250)', 'nR1-12', '1250,00', 'nR2-12', '1250,01', '-', '-'),
        ]);

        try {
            $faixas = (new LouosQuadro7EnquadramentosConverter)->converter($csv);
        } finally {
            unlink($csv);
        }

        $this->assertSame([
            ['cnae' => '4712-1/00', 'grupo' => 'nR1', 'subgrupo' => 'nR1-01', 'area_min' => '0', 'area_max' => '350', 'observacao' => '07.01.05'],
            ['cnae' => '4712-1/00', 'grupo' => 'nR2', 'subgrupo' => 'nR2-01', 'area_min' => '350.01', 'area_max' => '', 'observacao' => '07.01.05'],
        ], $faixas);
    }

    public function test_tres_faixas_de_area_ficam_contiguas_sem_sobrepor(): void
    {
        $csv = $this->csvTemporario([
            self::HEADER,
            $this->linha('0161-0/99', '07.08.07', 'nR1-08 (até 500) ; nR2-08 (de 501 a 5000) ; nR3-08 (acima de 5000)', 'nR1-08', '500,00', 'nR2-08', '5000', 'nR3-08', '5000,01'),
        ]);

        try {
            $faixas = (new LouosQuadro7EnquadramentosConverter)->converter($csv);
        } finally {
            unlink($csv);
        }

        $this->assertSame([
            ['cnae' => '0161-0/99', 'grupo' => 'nR1', 'subgrupo' => 'nR1-08', 'area_min' => '0', 'area_max' => '500', 'observacao' => '07.08.07'],
            ['cnae' => '0161-0/99', 'grupo' => 'nR2', 'subgrupo' => 'nR2-08', 'area_min' => '500.01', 'area_max' => '5000', 'observacao' => '07.08.07'],
            ['cnae' => '0161-0/99', 'grupo' => 'nR3', 'subgrupo' => 'nR3-08', 'area_min' => '5000.01', 'area_max' => '', 'observacao' => '07.08.07'],
        ], $faixas);
    }

    public function test_normaliza_subgrupo_e_ignora_9900_8_00(): void
    {
        $csv = $this->csvTemporario([
            self::HEADER,
            $this->linha('7711-0/00', '07.08.01', 'nR1-8 (até 350)', 'nR1-8', '350,00', 'nR2-012', '350,01', '-', '-'),
            $this->linha('9900-8/00', '07.11.02', 'nR2-11 (Qualquer área)', 'nR2-11', 'Q', '-', 'Q', '-', '-'),
        ]);

        try {
            $faixas = (new LouosQuadro7EnquadramentosConverter)->converter($csv);
        } finally {
            unlink($csv);
        }

        $this->assertSame([
            ['cnae' => '7711-0/00', 'grupo' => 'nR1', 'subgrupo' => 'nR1-08', 'area_min' => '0', 'area_max' => '350', 'observacao' => '07.08.01'],
            ['cnae' => '7711-0/00', 'grupo' => 'nR2', 'subgrupo' => 'nR2-12', 'area_min' => '350.01', 'area_max' => '', 'observacao' => '07.08.01'],
        ], $faixas);
    }

    public function test_planilha_real_cobre_o_catalogo_cnae_2_3(): void
    {
        $faixas = (new LouosQuadro7EnquadramentosConverter)->converter(
            database_path('data/regras-20-08-26/cnae-enquadramentos.csv'),
        );

        $cnaes = [];
        foreach ($faixas as $faixa) {
            $cnaes[preg_replace('/\D/', '', $faixa['cnae'])] = true;
        }

        $catalogo = [];
        $arquivo = new \SplFileObject(database_path('data/cnaes-subclasses-2-3.csv'), 'r');
        $arquivo->setFlags(\SplFileObject::READ_CSV | \SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY);
        $header = null;

        foreach ($arquivo as $linha) {
            if ($linha === false || $linha === [null]) {
                continue;
            }

            if ($header === null) {
                $header = $linha;

                continue;
            }

            $catalogo[preg_replace('/\D/', '', (string) $linha[8])] = true;
        }

        $this->assertSame(1331, count($catalogo));
        $this->assertSame([], array_values(array_diff(array_keys($catalogo), array_keys($cnaes))));
        $this->assertArrayNotHasKey('9900800', $cnaes);
        $this->assertGreaterThan(1331, count($faixas));
    }

    private function linha(
        string $cnae,
        string $codigoLouos,
        string $texto,
        string $e1,
        string $a1,
        string $e2,
        string $a2,
        string $e3,
        string $acima,
    ): string {
        return implode(',', [
            $cnae,
            'Denom',
            'BAIXO RISCO',
            'Regra 4',
            $codigoLouos,
            'Uso',
            '"'.$texto.'"',
            $e1,
            '"'.$a1.'"',
            $e2,
            '"'.$a2.'"',
            $e3,
            '"'.$acima.'"',
            '1.01',
            'TLL',
            'VALIDAÇÃO',
        ]);
    }

    /**
     * @param  array<int, string>  $linhas
     */
    private function csvTemporario(array $linhas): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'enq-');
        file_put_contents($caminho, implode("\n", $linhas)."\n");

        return $caminho;
    }
}
