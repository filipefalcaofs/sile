<?php

namespace Tests\Support\Analise;

use App\Services\Analise\PrecedentRepository;
use DateTimeInterface;

/**
 * Fake em memória do contrato dos precedentes (HU-142) — HELPER DE TESTE, não
 * código de produção (igual ao Tests\Support\Geo\FakeSpatialRepository). Permite
 * registrar as respostas (`setPropertyPrecedents`/`setCnaeZoneStats`) para
 * unit-testar o PrecedentService (e, à frente, o endpoint 10-09 e o smoke 10-18)
 * SEM PostGIS. Espelha a implementação real aplicando o `$limit` (LIMIT do SQL) e
 * registra as chamadas (`propertyCalls`/`cnaeZoneCalls`) para provar, sem
 * fachada, que a falta de zona NÃO dispara a consulta da estatística.
 */
class FakePrecedentRepository implements PrecedentRepository
{
    /** @var list<array{viability_request_id: int, protocol_number: ?string, outcome: string, decided_at: ?string, service_type: ?string, analyst: ?string}> */
    private array $propertyPrecedents = [];

    /** @var array<string, array{deferidos: int, indeferidos: int, total: int}> */
    private array $cnaeZoneStats = [];

    /** @var list<array{geojson: array<string, mixed>, limit: int, fallback_street: ?string, fallback_number: ?string}> */
    public array $propertyCalls = [];

    /** @var list<array{cnae: string, zona: string, since: DateTimeInterface}> */
    public array $cnaeZoneCalls = [];

    /**
     * @param  list<array{viability_request_id: int, protocol_number: ?string, outcome: string, decided_at: ?string, service_type: ?string, analyst: ?string}>  $rows
     */
    public function setPropertyPrecedents(array $rows): void
    {
        $this->propertyPrecedents = $rows;
    }

    /**
     * @param  array{deferidos: int, indeferidos: int, total: int}  $stats
     */
    public function setCnaeZoneStats(string $cnae, string $zona, array $stats): void
    {
        $this->cnaeZoneStats[$cnae.'|'.$zona] = $stats;
    }

    public function propertyPrecedents(array $currentGeojson, int $limit, ?string $fallbackStreet = null, ?string $fallbackNumber = null): array
    {
        $this->propertyCalls[] = [
            'geojson' => $currentGeojson,
            'limit' => $limit,
            'fallback_street' => $fallbackStreet,
            'fallback_number' => $fallbackNumber,
        ];

        return array_slice($this->propertyPrecedents, 0, $limit);
    }

    public function cnaeZoneStats(string $cnae, string $zona, DateTimeInterface $since): array
    {
        $this->cnaeZoneCalls[] = [
            'cnae' => $cnae,
            'zona' => $zona,
            'since' => $since,
        ];

        return $this->cnaeZoneStats[$cnae.'|'.$zona] ?? ['deferidos' => 0, 'indeferidos' => 0, 'total' => 0];
    }
}
