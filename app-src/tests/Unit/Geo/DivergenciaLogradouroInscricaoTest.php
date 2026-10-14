<?php

namespace Tests\Unit\Geo;

use App\Services\Geo\DivergenciaLogradouroInscricao;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DivergenciaLogradouroInscricaoTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function logradourosDivergentes(): array
    {
        return [
            'ruas diferentes' => ['SÃO CRISTÓVÃO', 'Rua São Geraldo'],
            'mesmo tipo, nomes diferentes' => ['Rua das Flores', 'Rua dos Cravos'],
        ];
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function logradourosEquivalentes(): array
    {
        return [
            'iguais' => ['Rua São Geraldo', 'Rua São Geraldo'],
            'caixa e acento' => ['SAO GERALDO', 'Rua São Geraldo'],
            'tipo abreviado' => ['Av. Antonio Carlos Magalhaes', 'Avenida Antônio Carlos Magalhães'],
            'preposições' => ['Rua Barão de Cotegipe', 'Rua Barao Cotegipe'],
            'um contém o outro' => ['Antônio Carlos Magalhães', 'Governador Antônio Carlos Magalhães'],
            'declarado vazio' => [null, 'Rua São Geraldo'],
            'declarado em branco' => ['   ', 'Rua São Geraldo'],
            'tributário ausente' => ['Rua São Geraldo', null],
            'ambos ausentes' => [null, null],
        ];
    }

    #[DataProvider('logradourosDivergentes')]
    public function test_aponta_divergencia_quando_os_logradouros_sao_outros(?string $declarado, ?string $tributario): void
    {
        $this->assertTrue((new DivergenciaLogradouroInscricao)->diverge($declarado, $tributario));
    }

    #[DataProvider('logradourosEquivalentes')]
    public function test_nao_aponta_divergencia_quando_equivalem_ou_falta_dado(?string $declarado, ?string $tributario): void
    {
        $this->assertFalse((new DivergenciaLogradouroInscricao)->diverge($declarado, $tributario));
    }
}
