<?php

namespace Tests\Support\Geo;

use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Services\Geo\SpatialRepository;

/**
 * Fake em memória do contrato espacial: permite registrar respostas por tipo
 * de camada (`setContaining`/`setNearest`/`setIntersecting`) para unit-testar
 * os consumidores do `TerritoryService` SEM PostGIS. Registra também quais
 * tipos foram consultados (`containingCalls`/`nearestCalls`/`intersectingCalls`)
 * para provar, sem fachada, que camadas bloqueadas (zona/lote) NÃO disparam
 * consulta espacial.
 */
class FakeSpatialRepository implements SpatialRepository
{
    /** @var array<string, array{id: int, properties: array<string, mixed>}|null> */
    private array $containing = [];

    /** @var array<string, array{id: int, properties: array<string, mixed>, distancia_m: float}|null> */
    private array $nearest = [];

    /** @var array<string, array<int, array{id: int, properties: array<string, mixed>}>> */
    private array $intersecting = [];

    /** @var array<int, string> */
    public array $containingCalls = [];

    /** @var array<int, string> */
    public array $nearestCalls = [];

    /** @var array<int, string> */
    public array $intersectingCalls = [];

    /**
     * @param  array{id: int, properties: array<string, mixed>}|null  $response
     */
    public function setContaining(GeoLayerType $type, ?array $response): void
    {
        $this->containing[$type->value] = $response;
    }

    /**
     * @param  array{id: int, properties: array<string, mixed>, distancia_m: float}|null  $response
     */
    public function setNearest(GeoLayerType $type, ?array $response): void
    {
        $this->nearest[$type->value] = $response;
    }

    /**
     * @param  array<int, array{id: int, properties: array<string, mixed>}>  $responses
     */
    public function setIntersecting(GeoLayerType $type, array $responses): void
    {
        $this->intersecting[$type->value] = $responses;
    }

    public function containingFeature(GeoLayer $layer, float $lng, float $lat): ?array
    {
        $this->containingCalls[] = $layer->type->value;

        return $this->containing[$layer->type->value] ?? null;
    }

    public function nearestFeature(GeoLayer $layer, float $lng, float $lat, int $maxMeters): ?array
    {
        $this->nearestCalls[] = $layer->type->value;

        return $this->nearest[$layer->type->value] ?? null;
    }

    public function intersectingFeatures(GeoLayer $layer, float $lng, float $lat): array
    {
        $this->intersectingCalls[] = $layer->type->value;

        return $this->intersecting[$layer->type->value] ?? [];
    }
}
