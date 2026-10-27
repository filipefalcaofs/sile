<?php

namespace App\Services\Geo;

/**
 * Zona urbanística de um polígono pela maior porção. A implementação real
 * consulta o GeoServer SEDUR e calcula no PostGIS; a suíte SQLite usa um fake.
 */
interface ZonaMaiorPorcaoResolver
{
    /**
     * @param  array<string, mixed>  $poligono  GeoJSON Polygon/MultiPolygon em EPSG:4326
     * @param  string|null  $codLog  CODLOG do endereço do processo, para o lote lindeiro à centralidade linear
     */
    public function resolver(array $poligono, ?string $codLog = null): ZonaMaiorPorcao;
}
