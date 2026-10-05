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
     */
    public function resolver(array $poligono): ZonaMaiorPorcao;
}
