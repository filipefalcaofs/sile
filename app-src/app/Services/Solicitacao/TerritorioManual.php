<?php

namespace App\Services\Solicitacao;

use App\Services\Geo\TerritoryResult;

/**
 * Aplica ao território identificado a zona e a via escolhidas à mão na ficha de
 * análise. A escolha manual vale sobre a calculada.
 */
final class TerritorioManual
{
    public static function aplicar(TerritoryResult $base, ?string $zona, ?string $classeVia): TerritoryResult
    {
        $zona = self::texto($zona);
        $classeVia = self::texto($classeVia);

        if ($zona === null && $classeVia === null) {
            return $base;
        }

        return new TerritoryResult(
            bairro: $base->bairro,
            via: $classeVia === null ? $base->via : self::via($base->via, $classeVia),
            zona: $zona === null ? $base->zona : [
                'status' => 'identificado',
                'nome' => $zona,
                'propriedades' => ['ZONA' => $zona, 'fonte' => 'manual'],
                'motivo' => null,
                'versao_camada' => 'manual',
            ],
            lote: $base->lote,
            restricoes: $base->restricoes,
        );
    }

    /**
     * @param  array<string, mixed>  $via
     * @return array<string, mixed>
     */
    private static function via(array $via, string $classeVia): array
    {
        $propriedades = is_array($via['propriedades'] ?? null) ? $via['propriedades'] : [];

        return [
            ...$via,
            'status' => 'identificado',
            'propriedades' => [...$propriedades, 'CLASSE_VIA_LOUOS' => $classeVia, 'fonte' => 'manual'],
            'motivo' => null,
            'classe_via_motivo' => null,
            'versao_camada' => 'manual',
            'distancia_m' => $via['distancia_m'] ?? null,
        ];
    }

    private static function texto(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
