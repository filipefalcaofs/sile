<?php

namespace App\Services\Geo\Edificacoes;

/**
 * Decide se o contorno gerado por IA vira polígono: precisa conter o ponto do
 * imóvel, ter área plausível de edificação, ser compacto e não encostar na
 * borda do recorte (prédio cortado). Recusado, nada é gravado.
 */
class AceiteMascaraIa
{
    /**
     * @return array{aceita: bool, motivo: string|null, geojson: array<string, mixed>|null, area_m2: float|null}
     */
    public function avaliar(MascaraIa $mascara, RecorteOrtofoto $recorte, float $lat, float $lng): array
    {
        $anel = $mascara->anelPixel;

        if ($mascara->status !== MascaraIa::OK || count($anel) < 3) {
            return $this->recusa('sem_objeto');
        }

        $margem = (float) config('viabiliza.edificacoes.ia.aceite.margem_borda_px', 2);

        foreach ($anel as [$x, $y]) {
            if ($x <= $margem || $y <= $margem || $x >= $recorte->largura - 1 - $margem || $y >= $recorte->altura - 1 - $margem) {
                return $this->recusa('encosta_na_borda');
            }
        }

        $coordenadas = array_map(fn (array $pixel): array => $recorte->coordenada((float) $pixel[0], (float) $pixel[1]), $anel);

        if (! $this->contem($coordenadas, $lng, $lat)) {
            return $this->recusa('nao_contem_o_ponto');
        }

        [$area, $perimetro] = $this->medidas($coordenadas, $lat);

        if ($area < (float) config('viabiliza.edificacoes.ia.aceite.area_min_m2', 20)
            || $area > (float) config('viabiliza.edificacoes.ia.aceite.area_max_m2', 20000)) {
            return $this->recusa('area_fora_dos_limites', $area);
        }

        if ($perimetro <= 0 || 4 * M_PI * $area / ($perimetro ** 2) < (float) config('viabiliza.edificacoes.ia.aceite.compacidade_min', 0.2)) {
            return $this->recusa('compacidade_baixa', $area);
        }

        $scoreMinimo = config('viabiliza.edificacoes.ia.aceite.score_min');

        if (is_numeric($scoreMinimo) && $mascara->score !== null && $mascara->score < (float) $scoreMinimo) {
            return $this->recusa('score_baixo', $area);
        }

        $coordenadas[] = $coordenadas[0];

        return [
            'aceita' => true,
            'motivo' => null,
            'geojson' => ['type' => 'MultiPolygon', 'coordinates' => [[$coordenadas]]],
            'area_m2' => round($area, 2),
        ];
    }

    /**
     * @return array{aceita: false, motivo: string, geojson: null, area_m2: float|null}
     */
    private function recusa(string $motivo, ?float $area = null): array
    {
        return ['aceita' => false, 'motivo' => $motivo, 'geojson' => null, 'area_m2' => $area === null ? null : round($area, 2)];
    }

    /**
     * Ponto no polígono por cruzamento de raio.
     *
     * @param  list<array{0: float, 1: float}>  $anel
     */
    private function contem(array $anel, float $lng, float $lat): bool
    {
        $dentro = false;
        $total = count($anel);

        for ($i = 0, $j = $total - 1; $i < $total; $j = $i++) {
            [$xi, $yi] = $anel[$i];
            [$xj, $yj] = $anel[$j];

            if ((($yi > $lat) !== ($yj > $lat)) && ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) {
                $dentro = ! $dentro;
            }
        }

        return $dentro;
    }

    /**
     * Área e perímetro em metros por projeção equirretangular local, suficiente
     * para recortes de dezenas de metros.
     *
     * @param  list<array{0: float, 1: float}>  $anel
     * @return array{0: float, 1: float}
     */
    private function medidas(array $anel, float $latRef): array
    {
        $mPorGrauLng = 111320 * cos(deg2rad($latRef));
        $mPorGrauLat = 110574;
        $pontos = array_map(fn (array $c): array => [$c[0] * $mPorGrauLng, $c[1] * $mPorGrauLat], $anel);
        $soma = 0.0;
        $perimetro = 0.0;
        $total = count($pontos);

        for ($i = 0; $i < $total; $i++) {
            [$x1, $y1] = $pontos[$i];
            [$x2, $y2] = $pontos[($i + 1) % $total];
            $soma += $x1 * $y2 - $x2 * $y1;
            $perimetro += hypot($x2 - $x1, $y2 - $y1);
        }

        return [abs($soma) / 2, $perimetro];
    }
}
