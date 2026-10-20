<?php

namespace App\Services\Geo;

use App\Models\GeoLayer;

/**
 * Contrato que isola TODO o SQL espacial PostGIS da identificação territorial
 * (HU-030 a HU-034). Cada método recebe a `GeoLayer` JÁ RESOLVIDA (versão
 * vigente ou da data da decisão — HU-035 RN-004), de modo que a vigência fica
 * a cargo do `TerritoryService` e o repositório só consulta a carga indicada.
 *
 * O ponto é sempre passado na ordem (longitude, latitude) — ST_MakePoint usa
 * [lng, lat] (Pitfall 3). A implementação real é `PostgisSpatialRepository`;
 * os consumidores (TerritoryService, motor da Fase 5, controllers) podem ser
 * testados com um fake em memória, sem PostGIS.
 */
interface SpatialRepository
{
    /**
     * Feição da camada que CONTÉM o ponto (ST_Contains) — bairro/zona/lote.
     *
     * @return array{id: int, properties: array<string, mixed>}|null
     */
    public function containingFeature(GeoLayer $layer, float $lng, float $lat): ?array;

    /**
     * Feição mais PRÓXIMA do ponto dentro de `$maxMeters` metros
     * (ST_DWithin/ST_Distance com ::geography) — via mais próxima.
     *
     * @return array{id: int, properties: array<string, mixed>, distancia_m: float}|null
     */
    public function nearestFeature(GeoLayer $layer, float $lng, float $lat, int $maxMeters): ?array;

    /**
     * Todas as feições até `$maxMeters` do ponto, da mais próxima para a mais
     * distante (ex.: todos os logradouros em volta do imóvel).
     *
     * @return list<array{id: int, properties: array<string, mixed>, distancia_m: float}>
     */
    public function featuresWithin(GeoLayer $layer, float $lng, float $lat, int $maxMeters, int $limit = 20): array;

    /**
     * Todas as feições que INTERCEPTAM o ponto (ST_Intersects) — restrições.
     *
     * @return array<int, array{id: int, properties: array<string, mixed>}>
     */
    public function intersectingFeatures(GeoLayer $layer, float $lng, float $lat): array;
}
