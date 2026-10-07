<?php

namespace App\Services\Tratamento;

/**
 * Capacidade de armazenamento de GLP declarada nas perguntas 26 a 29 da
 * planilha (regra 53): plataformas (P27) vezes a capacidade de cada uma
 * (P28), ou a capacidade de armazenamento direta (P29).
 */
final class ArmazenamentoGlp
{
    /**
     * Opção da P28 e da P29 → quilos. A opção 8 é "mais de 99.840 kg".
     *
     * @var array<int, int>
     */
    private const CAPACIDADE_KG = [
        1 => 520,
        2 => 1560,
        3 => 6240,
        4 => 12480,
        5 => 24960,
        6 => 49920,
        7 => 99840,
        8 => 99841,
    ];

    /**
     * P27: A = 2 plataformas, B = 3 plataformas.
     *
     * @var array<int, int>
     */
    private const PLATAFORMAS = [1 => 2, 2 => 3];

    /**
     * @param  array<int, bool|int|list<int>>  $respostas
     */
    public static function quilos(array $respostas): ?int
    {
        if (! array_key_exists(26, $respostas)) {
            return null;
        }

        if (RespostaPlanilha::de($respostas[26])->comoBooleano() === false) {
            return self::capacidade($respostas, 29);
        }

        $plataformas = self::PLATAFORMAS[self::opcao($respostas, 27) ?? 0] ?? null;
        $capacidade = self::capacidade($respostas, 28);

        return $plataformas === null || $capacidade === null ? null : $plataformas * $capacidade;
    }

    /**
     * @param  array<int, bool|int|list<int>>  $respostas
     */
    private static function capacidade(array $respostas, int $pergunta): ?int
    {
        return self::CAPACIDADE_KG[self::opcao($respostas, $pergunta) ?? 0] ?? null;
    }

    /**
     * @param  array<int, bool|int|list<int>>  $respostas
     */
    private static function opcao(array $respostas, int $pergunta): ?int
    {
        if (! array_key_exists($pergunta, $respostas)) {
            return null;
        }

        return RespostaPlanilha::de($respostas[$pergunta])->opcoes()[0] ?? null;
    }
}
