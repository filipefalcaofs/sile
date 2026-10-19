<?php

namespace App\Services\Geo\Edificacoes;

use App\Enums\FonteEdificacao;

/**
 * Base local de edificações complementares (building_footprints). Contrato
 * para o SQL espacial do PostGIS; os testes SQLite usam um fake em memória.
 */
interface BuildingFootprintRepository
{
    /**
     * Edificação ativa da fonte que contém o ponto; entre sobrepostas, a de
     * menor área. Ponto fora de qualquer edificação devolve nulo.
     */
    public function contendoPonto(FonteEdificacao $fonte, float $lat, float $lng): ?EdificacaoCandidata;

    /**
     * Corrige a topologia, converte para MultiPolygon e calcula a área.
     *
     * @param  array<string, mixed>  $geojson
     * @return array{valida: bool, geojson: array<string, mixed>|null, area_m2: float|null, notas: list<string>}
     */
    public function normalizar(array $geojson): array;

    /**
     * O ponto interior da geometria está no limite vigente do município?
     *
     * @param  array<string, mixed>  $geojson
     */
    public function dentroDoLimite(array $geojson): bool;

    public function limiteDisponivel(): bool;

    /**
     * Id da edificação Overture ativa que coincide com a geometria (IoU acima do limiar).
     *
     * @param  array<string, mixed>  $geojson
     */
    public function duplicadoOverture(array $geojson, float $limiar = 0.5): ?int;

    /**
     * Grava ou atualiza pela fonte e pelo id original, reativando a linha.
     *
     * @param  array<string, mixed>  $geojson  MultiPolygon já normalizado
     * @param  list<array<string, mixed>>|null  $sourceDatasets
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $notas
     * @return array{id: int, inserido: bool}
     */
    public function upsert(
        FonteEdificacao $fonte,
        string $sourceId,
        string $release,
        array $geojson,
        float $areaM2,
        ?float $confidence,
        ?array $sourceDatasets,
        array $properties,
        ?int $importId,
        ?int $duplicateOfId,
        array $notas,
    ): array;

    /**
     * Marca como aposentadas as edificações ativas da fonte que não vieram na importação.
     */
    public function aposentarAusentes(FonteEdificacao $fonte, int $importId): int;
}
