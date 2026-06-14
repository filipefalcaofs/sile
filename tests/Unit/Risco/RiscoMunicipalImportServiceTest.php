<?php

namespace Tests\Unit\Risco;

use App\Enums\RiscoMunicipal;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Dimensão municipal de risco (HU-020/HU-047) a partir do dado oficial real
 * (Decreto nº 32.636/2020). O Decreto só tem três níveis — NÃO existe "médio"
 * (regra firme do analista-negocio). fromDecreto() mapeia os rótulos do CSV e
 * rejeita qualquer nível desconhecido (nunca inventa classificação).
 */
class RiscoMunicipalImportServiceTest extends TestCase
{
    public function test_enum_from_decreto_mapeia_rotulos_oficiais(): void
    {
        $this->assertSame(RiscoMunicipal::BaixoA, RiscoMunicipal::fromDecreto('BAIXO A'));
        $this->assertSame(RiscoMunicipal::BaixoB, RiscoMunicipal::fromDecreto('BAIXO B'));
        $this->assertSame(RiscoMunicipal::Alto, RiscoMunicipal::fromDecreto('ALTO'));

        // Tolera ruído de formatação da planilha oficial (espaços/caixa).
        $this->assertSame(RiscoMunicipal::BaixoA, RiscoMunicipal::fromDecreto('  baixo a '));

        // "MÉDIO" não existe no Decreto — nível desconhecido lança exceção,
        // nunca é inventado.
        $this->expectException(InvalidArgumentException::class);
        RiscoMunicipal::fromDecreto('MÉDIO');
    }
}
