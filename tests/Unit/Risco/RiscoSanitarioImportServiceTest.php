<?php

namespace Tests\Unit\Risco;

use App\Enums\RiscoSanitario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Dimensão SANITÁRIA de risco (HU-019/HU-047/HU-048) a partir do dado oficial
 * real (planilha unificada CNAE da Vigilância Sanitária). A VISA tem três
 * níveis próprios — Baixo, Médio e Alto — DISTINTOS dos níveis municipais
 * (baixo_a/baixo_b/alto): aqui existe "médio". fromVisa() mapeia os rótulos da
 * planilha e rejeita qualquer nível desconhecido (nunca inventa classificação).
 * O import é provado sobre o CSV REAL commitado em database/data/risco/.
 */
class RiscoSanitarioImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_enum_from_visa_mapeia_niveis(): void
    {
        $this->assertSame(RiscoSanitario::Baixo, RiscoSanitario::fromVisa('Baixo'));
        $this->assertSame(RiscoSanitario::Medio, RiscoSanitario::fromVisa('Médio'));
        $this->assertSame(RiscoSanitario::Alto, RiscoSanitario::fromVisa('Alto'));

        // Tolera ruído de formatação da planilha oficial (espaços/caixa/acento).
        $this->assertSame(RiscoSanitario::Medio, RiscoSanitario::fromVisa('  MÉDIO '));
        $this->assertSame(RiscoSanitario::Baixo, RiscoSanitario::fromVisa('baixo'));

        // Níveis sanitários são distintos dos municipais (existe "médio").
        $this->assertSame('medio', RiscoSanitario::Medio->value);

        // Nível desconhecido lança exceção, nunca é inventado.
        $this->expectException(InvalidArgumentException::class);
        RiscoSanitario::fromVisa('Altíssimo');
    }
}
