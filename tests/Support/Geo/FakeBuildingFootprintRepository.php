<?php

namespace Tests\Support\Geo;

use App\Enums\FonteEdificacao;
use App\Services\Geo\Edificacoes\BuildingFootprintRepository;
use App\Services\Geo\Edificacoes\EdificacaoCandidata;
use Illuminate\Support\Facades\DB;
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

    /**
     * @param  array<string, mixed>  $geojson
     */
    public function comCandidata(FonteEdificacao $fonte, array $geojson, float $areaM2 = 120.0, ?float $confidence = null, string $metodo = EdificacaoCandidata::CONTEM_PONTO): static
    {
        $this->candidatas[$fonte->value] = $this->candidata($fonte, $geojson, $areaM2, $confidence, $metodo);

        return $this;
    }

    /**
     * Candidata com linha real em building_footprints (a procedência aponta
     * para ela); em SQLite a geometria é só um marcador.
     *
     * @param  array<string, mixed>  $geojson
     */
    public function candidata(FonteEdificacao $fonte, array $geojson, float $areaM2 = 120.0, ?float $confidence = null, string $metodo = EdificacaoCandidata::CONTEM_PONTO): EdificacaoCandidata
    {
        $sourceId = $fonte->value.'-'.uniqid();
        $id = DB::table('building_footprints')->insertGetId([
            'source' => $fonte->value,
            'source_id' => $sourceId,
            'release' => 'release-teste',
            'geometry' => json_encode($geojson),
            'area_m2' => $areaM2,
            'confidence' => $confidence,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new EdificacaoCandidata($id, $fonte, $sourceId, 'release-teste', $geojson, $areaM2, $confidence, $metodo);
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
        $candidata = $this->candidata($fonte, $geojson, $areaM2, $confidence);
        $this->candidatas[$fonte->value] = $candidata;

        return ['id' => $candidata->id, 'inserido' => true];
    }

    public function aposentarAusentes(FonteEdificacao $fonte, int $importId): int
    {
        return 0;
    }
}
