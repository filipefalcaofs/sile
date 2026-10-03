<?php

namespace App\Services\Geo;

interface ZonaIntersecaoMedidor
{
    /**
     * Área da interseção, em m², de cada candidata com o lote.
     * Null quando a medição não pode ser feita (sem PostGIS): não aproxima.
     *
     * @param  array<string, mixed>  $lote
     * @param  list<array{zona: string, geometria: array<string, mixed>, linear: bool}>  $candidatas
     * @return list<array{zona: string, area_m2: float, linear: bool}>|null
     */
    public function medir(array $lote, array $candidatas): ?array;
}
