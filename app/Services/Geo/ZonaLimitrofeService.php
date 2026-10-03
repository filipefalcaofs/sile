<?php

namespace App\Services\Geo;

/**
 * Aplica o art. 18, § 2º, ao polígono do lote. Sem polígono, não escolhe zona
 * e não desenha geometria a partir do ponto ou da área.
 */
class ZonaLimitrofeService
{
    private ?string $memoChave = null;

    private ?ZonaLimitrofeAvaliacao $memo = null;

    public function __construct(
        private ZonaPorcaoDecisor $decisor,
        private ZonaGeometriaColetor $coletor,
        private ZonaIntersecaoMedidor $medidor,
    ) {}

    /**
     * @param  array<string, mixed>|null  $poligono
     */
    public function avaliar(?array $poligono): ZonaLimitrofeAvaliacao
    {
        $chave = $poligono === null ? 'sem' : md5((string) json_encode($poligono));

        if ($this->memoChave === $chave && $this->memo !== null) {
            return $this->memo;
        }

        $this->memoChave = $chave;

        return $this->memo = $this->calcular($poligono);
    }

    /**
     * @param  array<string, mixed>|null  $poligono
     */
    private function calcular(?array $poligono): ZonaLimitrofeAvaliacao
    {
        if (! is_array($poligono) || ! $this->poligonoDeArea($poligono)) {
            return ZonaLimitrofeAvaliacao::semPoligono();
        }

        if (($poligono['type'] ?? null) === 'Feature' && is_array($poligono['geometry'] ?? null)) {
            $poligono = $poligono['geometry'];
        }

        $candidatas = $this->coletor->noPoligono($poligono);

        if ($candidatas === null) {
            return ZonaLimitrofeAvaliacao::indisponivel(
                'Não foi possível consultar as zonas de uso para medir a maior porção do lote.',
            );
        }

        $medicoes = $this->medidor->medir($poligono, $candidatas);

        if ($medicoes === null) {
            return ZonaLimitrofeAvaliacao::indisponivel(
                'Não foi possível medir a interseção do lote com as zonas. O cálculo exige PostGIS.',
            );
        }

        return $this->decisor->decidir($medicoes);
    }

    /**
     * @param  array<string, mixed>|null  $poligono
     */
    private function poligonoDeArea(?array $poligono): bool
    {
        if ($poligono === null) {
            return false;
        }

        $tipo = $poligono['type'] ?? null;

        if ($tipo === 'Feature' && is_array($poligono['geometry'] ?? null)) {
            return $this->poligonoDeArea($poligono['geometry']);
        }

        return in_array($tipo, ['Polygon', 'MultiPolygon'], true)
            && is_array($poligono['coordinates'] ?? null)
            && $poligono['coordinates'] !== [];
    }
}
