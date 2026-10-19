<?php

namespace Tests\Support\Geo;

use App\Enums\FonteEdificacao;
use App\Services\Geo\Edificacoes\BuildingFootprintRepository;
use App\Services\Geo\Edificacoes\EdificacaoCandidata;
use RuntimeException;

/**
 * Base de edificações em memória para os testes SQLite: cada fonte devolve a
 * candidata pré-carregada (ou nada), sem SQL espacial.
 */
class FakeBuildingFootprintRepository implements BuildingFootprintRepository
{
    /** @var array<string, EdificacaoCandidata|null> */
    public array $candidatas = [];

    /** @var array<string, bool> */
    public array $falhas = [];

    /** @var list<string> */
    public array $consultas = [];

    public function comCandidata(FonteEdificacao $fonte, array $geojson, float $areaM2 = 120.0, ?float $confidence = null, string $metodo = EdificacaoCandidata::CONTEM_PONTO, int $id = 1): static
    {
        $this->candidatas[$fonte->value] = new EdificacaoCandidata($id, $fonte, "{$fonte->value}-{$id}", 'release-teste', $geojson, $areaM2, $confidence, $metodo);

        return $this;
    }

    public function falhando(FonteEdificacao $fonte): static
    {
        $this->falhas[$fonte->value] = true;

        return $this;
    }

    public function contendoPonto(FonteEdificacao $fonte, float $lat, float $lng): ?EdificacaoCandidata
    {
        $this->consultas[] = $fonte->value;

        if ($this->falhas[$fonte->value] ?? false) {
            throw new RuntimeException("Base de edificações indisponível ({$fonte->value}).");
        }

        return $this->candidatas[$fonte->value] ?? null;
    }

    public function normalizar(array $geojson): array
    {
        return ['valida' => true, 'geojson' => $geojson, 'area_m2' => 120.0, 'notas' => []];
    }

    public function dentroDoLimite(array $geojson): bool
    {
        return true;
    }

    public function limiteDisponivel(): bool
    {
        return true;
    }

    public function duplicadoOverture(array $geojson, float $limiar = 0.5): ?int
    {
        return null;
    }

    public function upsert(FonteEdificacao $fonte, string $sourceId, string $release, array $geojson, float $areaM2, ?float $confidence, ?array $sourceDatasets, array $properties, ?int $importId, ?int $duplicateOfId, array $notas): array
    {
        $id = count($this->candidatas) + 100;
        $this->candidatas[$fonte->value] = new EdificacaoCandidata($id, $fonte, $sourceId, $release, $geojson, $areaM2, $confidence, EdificacaoCandidata::CONTEM_PONTO);

        return ['id' => $id, 'inserido' => true];
    }

    public function aposentarAusentes(FonteEdificacao $fonte, int $importId): int
    {
        return 0;
    }
}
