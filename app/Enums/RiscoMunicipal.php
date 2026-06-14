<?php

namespace App\Enums;

use InvalidArgumentException;

/**
 * Nível de risco municipal de uma atividade econômica conforme o Decreto
 * nº 32.636/2020 (HU-020/HU-047). O Decreto tem EXATAMENTE três níveis —
 * NÃO existe "médio" (é diretriz operacional, não classificação do Decreto;
 * regra firme do analista-negocio). fromDecreto() é a única porta de entrada
 * a partir do rótulo oficial e rejeita qualquer nível desconhecido, para nunca
 * inventar classificação (entrega funcional sem fachada).
 */
enum RiscoMunicipal: string
{
    case BaixoA = 'baixo_a';
    case BaixoB = 'baixo_b';
    case Alto = 'alto';

    public function label(): string
    {
        return match ($this) {
            self::BaixoA => 'Baixo Risco A',
            self::BaixoB => 'Baixo Risco B',
            self::Alto => 'Alto Risco',
        };
    }

    /**
     * Mapeia o rótulo cru do Decreto ('BAIXO A' / 'BAIXO B' / 'ALTO') para o
     * nível tipado. Tolera ruído de formatação da planilha oficial (espaços e
     * caixa). Nível desconhecido lança InvalidArgumentException com a string
     * crua — nunca cria um nível inexistente.
     */
    public static function fromDecreto(string $raw): self
    {
        $normalized = mb_strtoupper(trim($raw));

        return match ($normalized) {
            'BAIXO A' => self::BaixoA,
            'BAIXO B' => self::BaixoB,
            'ALTO' => self::Alto,
            default => throw new InvalidArgumentException(
                "Nível de risco municipal desconhecido no Decreto 32.636/2020: '{$raw}'.",
            ),
        };
    }
}
