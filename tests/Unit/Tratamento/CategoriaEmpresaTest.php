<?php

namespace Tests\Unit\Tratamento;

use App\Services\Tratamento\CategoriaEmpresa;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoriaEmpresaTest extends TestCase
{
    /**
     * @return array<string, array{?string, ?string, ?string}>
     */
    public static function naturezas(): array
    {
        return [
            'código da administração pública' => ['1015', null, 'orgao_publico'],
            'código com hífen' => ['101-5', null, 'orgao_publico'],
            'município' => ['1244', null, 'orgao_publico'],
            'sociedade limitada' => ['2062', null, 'outra'],
            'MEI' => ['2135', null, 'outra'],
            'só a descrição de órgão público' => [null, 'Órgão Público do Poder Executivo Municipal', 'orgao_publico'],
            'só a descrição de sociedade' => [null, 'Sociedade Empresária Limitada', 'outra'],
            'código no texto da descrição' => [null, '1015 - Órgão Público do Poder Executivo Federal', 'orgao_publico'],
            'sem informação' => [null, null, null],
            'vazio' => ['', '  ', null],
        ];
    }

    #[DataProvider('naturezas')]
    public function test_classifica_a_natureza_juridica(?string $codigo, ?string $descricao, ?string $esperada): void
    {
        $this->assertSame($esperada, CategoriaEmpresa::deNaturezaJuridica($codigo, $descricao));
    }

    public function test_separa_codigo_e_descricao_da_natureza_juridica_do_regin(): void
    {
        $this->assertSame(['codigo' => '1015', 'descricao' => 'Órgão Público do Poder Executivo Federal'], CategoriaEmpresa::separar('1015 - Órgão Público do Poder Executivo Federal'));
        $this->assertSame(['codigo' => '2062', 'descricao' => null], CategoriaEmpresa::separar('2062'));
        $this->assertSame(['codigo' => '2062', 'descricao' => null], CategoriaEmpresa::separar('206-2'));
        $this->assertSame(['codigo' => null, 'descricao' => 'Sociedade Empresária Limitada'], CategoriaEmpresa::separar('Sociedade Empresária Limitada'));
        $this->assertSame(['codigo' => null, 'descricao' => null], CategoriaEmpresa::separar('  '));
        $this->assertSame(['codigo' => null, 'descricao' => null], CategoriaEmpresa::separar(null));
    }
}
