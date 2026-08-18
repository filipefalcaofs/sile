<?php

namespace App\Enums;

use InvalidArgumentException;

/**
 * Nível de risco municipal de uma atividade econômica conforme o Decreto
 * nº 32.636/2020 (HU-020/HU-047). O Decreto tem EXATAMENTE três níveis
 * internos — baixo_a / baixo_b / alto — e fromDecreto() é a única porta de
 * entrada a partir do rótulo oficial ('BAIXO A' / 'BAIXO B' / 'ALTO'),
 * rejeitando qualquer nível desconhecido para nunca inventar classificação.
 *
 * A SEDUR EXIBE esses níveis como Baixo / Médio / Alto (nomenclatura
 * operacional da secretaria, ver label()). O rótulo de exibição é
 * desacoplado da chave interna e do parse do Decreto de propósito.
 */
enum RiscoMunicipal: string
{
    case BaixoA = 'baixo_a';
    case BaixoB = 'baixo_b';
    case Alto = 'alto';

    public function label(): string
    {
        return match ($this) {
            self::BaixoA => 'Baixo',
            self::BaixoB => 'Médio',
            self::Alto => 'Alto',
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
