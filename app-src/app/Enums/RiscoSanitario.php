<?php

namespace App\Enums;

use InvalidArgumentException;

/**
 * Nível de risco SANITÁRIO de uma atividade econômica conforme a planilha
 * unificada da Vigilância Sanitária (VISA), dimensão SEPARADA do risco
 * municipal do Decreto nº 32.636/2020 (HU-019/HU-047/HU-048). A VISA tem três
 * níveis próprios — Baixo, Médio e Alto — distintos dos níveis municipais
 * (baixo_a/baixo_b/alto): aqui existe "médio". fromVisa() é a única porta de
 * entrada a partir do rótulo oficial e rejeita qualquer nível desconhecido,
 * para nunca inventar classificação (entrega funcional sem fachada).
 */
enum RiscoSanitario: string
{
    case Baixo = 'baixo';
    case Medio = 'medio';
    case Alto = 'alto';

    public function label(): string
    {
        return match ($this) {
            self::Baixo => 'Baixo Risco',
            self::Medio => 'Médio Risco',
            self::Alto => 'Alto Risco',
        };
    }

    /**
     * Severidade relativa do nível (Baixo < Médio < Alto). Usada para resolver
     * o nível mais restritivo quando a planilha traz a mesma subclasse CNAE em
     * mais de uma linha (sub-atividades) — risco sanitário nunca é rebaixado
     * silenciosamente.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Baixo => 1,
            self::Medio => 2,
            self::Alto => 3,
        };
    }

    /**
     * Mapeia o rótulo cru da planilha VISA ('Baixo' / 'Médio' / 'Alto') para o
     * nível tipado. Tolera ruído de formatação (espaços, caixa e acento em
     * 'MÉDIO'). Nível desconhecido lança InvalidArgumentException com a string
     * crua — nunca cria um nível inexistente.
     */
    public static function fromVisa(string $raw): self
    {
        $normalized = strtr(mb_strtoupper(trim($raw)), [
            'É' => 'E',
            'Ê' => 'E',
            'Ã' => 'A',
            'Á' => 'A',
            'À' => 'A',
            'Â' => 'A',
        ]);

        return match ($normalized) {
            'BAIXO' => self::Baixo,
            'MEDIO' => self::Medio,
            'ALTO' => self::Alto,
            default => throw new InvalidArgumentException(
                "Nível de risco sanitário desconhecido na planilha VISA: '{$raw}'.",
            ),
        };
    }
}
