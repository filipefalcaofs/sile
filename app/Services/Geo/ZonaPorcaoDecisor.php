<?php

namespace App\Services\Geo;

/**
 * Compara as áreas já medidas da interseção do lote com cada zona.
 * Prevalece a maior, inclusive quando a diferença é menor que 1%.
 * Empate só na igualdade milimétrica (1 mm²): ruído de medição, não corte legal.
 * Zona linear não entra.
 */
final class ZonaPorcaoDecisor
{
    private const IGUALDADE_M2 = 0.000001;

    /**
     * @param  list<array{zona: string, area_m2: float, linear: bool}>  $medicoes
     */
    public function decidir(array $medicoes): ZonaLimitrofeAvaliacao
    {
        $areas = [];

        foreach ($medicoes as $medicao) {
            if ($medicao['linear'] || $medicao['area_m2'] <= 0) {
                continue;
            }

            $zona = $medicao['zona'];
            $areas[$zona] = ($areas[$zona] ?? 0.0) + $medicao['area_m2'];
        }

        if ($areas === []) {
            return new ZonaLimitrofeAvaliacao(
                status: ZonaLimitrofeAvaliacao::SEM_INTERSECAO,
                zonaVencedora: null,
                porcoes: [],
                empatadas: [],
                motivo: 'O polígono do lote não intersecta zona de uso com área.',
                fonte: ZonaLimitrofeAvaliacao::FONTE,
            );
        }

        arsort($areas);

        $total = array_sum($areas);
        $maior = (float) reset($areas);
        $porcoes = [];
        $empatadas = [];

        foreach ($areas as $zona => $area) {
            $porcoes[] = [
                'zona' => (string) $zona,
                'area_m2' => round($area, 4),
                'percentual' => round($area / $total * 100, 2),
            ];

            if (abs($area - $maior) <= self::IGUALDADE_M2) {
                $empatadas[] = (string) $zona;
            }
        }

        if (count($empatadas) > 1) {
            $lista = implode(' e ', $empatadas);

            return new ZonaLimitrofeAvaliacao(
                status: ZonaLimitrofeAvaliacao::EMPATE,
                zonaVencedora: null,
                porcoes: $porcoes,
                empatadas: $empatadas,
                motivo: "Empate de área entre as zonas {$lista}. A lei não escolhe; encaminhado à análise.",
                fonte: ZonaLimitrofeAvaliacao::FONTE,
            );
        }

        return new ZonaLimitrofeAvaliacao(
            status: ZonaLimitrofeAvaliacao::IDENTIFICADO,
            zonaVencedora: $empatadas[0],
            porcoes: $porcoes,
            empatadas: [],
            motivo: null,
            fonte: ZonaLimitrofeAvaliacao::FONTE,
        );
    }
}
