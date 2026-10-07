<?php

namespace Tests\Unit\Tratamento;

use App\Services\Tratamento\CnluLista;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CnluListaTest extends TestCase
{
    private const LISTA = ['nR3-08', 'nR3-14', 'nRa-02', 'nR4', 'ID3-02', 'ID3-07', 'ID3-13'];

    /**
     * @return array<string, array{string, bool}>
     */
    public static function subgrupos(): array
    {
        return [
            'igual' => ['nR3-08', true],
            'outro subgrupo do mesmo grupo fica fora' => ['nR3-01', false],
            'nRa só no subgrupo 02' => ['nRa-02', true],
            'nRa-01 fica fora' => ['nRa-01', false],
            'nR4 com todos os subgrupos' => ['nR4-04', true],
            'nR4 no próprio grupo' => ['nR4', true],
            'espaço depois do hífen' => ['ID3- 07', true],
            'sufixo do subgrupo 2' => ['ID3-02 (subgrupo 2)', true],
            'sufixo do subgrupo 1' => ['ID3-02 (subgrupo 1)', true],
            'sufixo malformado' => ['ID3-13 (subgrupo 1', true],
            'ID fora da lista' => ['ID3-06', false],
            'caixa diferente' => ['id3-07', true],
            'vazio' => ['', false],
        ];
    }

    #[DataProvider('subgrupos')]
    public function test_casa_o_subgrupo_da_linha_com_a_lista(string $subgrupo, bool $esperado): void
    {
        $this->assertSame($esperado, CnluLista::contem($subgrupo, self::LISTA));
    }
}
