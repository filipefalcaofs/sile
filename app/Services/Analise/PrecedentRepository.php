<?php

namespace App\Services\Analise;

use DateTimeInterface;

/**
 * Contrato que isola TODO o SQL espacial/agregado dos precedentes da análise
 * técnica (HU-142) — espelha o SpatialRepository da Fase 4. A implementação real
 * é o PostgisPrecedentRepository (ST_Intersects sobre a geometry property_polygon
 * derivada na Fase 8, @group postgis); os consumidores (PrecedentService e, à
 * frente, o endpoint da ficha 10-09 e o smoke 10-18) dependem da interface e são
 * testados com um fake em memória populado, sem PostGIS.
 *
 * O GeoJSON do imóvel atual entra como o Polygon do property_polygon_geojson
 * (anel de pares [lng, lat] — mesma ordem do ST_GeomFromGeoJSON). LGPD (RN-004):
 * a projeção NUNCA inclui dados pessoais do requerente (jamais CPF) — só
 * protocolo, desfecho, data, serviço e analista.
 */
interface PrecedentRepository
{
    /**
     * Decisões anteriores no MESMO imóvel: ST_Intersects entre o property_polygon
     * de cada solicitação decidida e o GeoJSON atual, ordenadas por decided_at
     * desc e limitadas a `$limit`. Sem polígono (ou sem PostGIS), cai no fallback
     * portável por endereço (street + number). Sem histórico → lista vazia.
     *
     * @param  array<string, mixed>  $currentGeojson  GeoJSON Polygon do imóvel atual (anel [lng,lat]); vazio força o fallback.
     * @return list<array{viability_request_id: int, protocol_number: ?string, outcome: string, decided_at: ?string, service_type: ?string, analyst: ?string}>
     */
    public function propertyPrecedents(array $currentGeojson, int $limit, ?string $fallbackStreet = null, ?string $fallbackNumber = null): array;

    /**
     * Agregação das decisões do CNAE na zona desde a data (janela parametrizada):
     * deferidos × indeferidos × total. Só é chamada quando há zona identificada —
     * sem zona, o consumidor degrada honesto (estatística "indisponível"), nunca
     * inventa o número.
     *
     * @return array{deferidos: int, indeferidos: int, total: int}
     */
    public function cnaeZoneStats(string $cnae, string $zona, DateTimeInterface $since): array;
}
